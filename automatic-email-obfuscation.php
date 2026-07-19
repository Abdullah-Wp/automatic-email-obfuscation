<?php
/**
 * Plugin Name: Automatic Email Obfuscation
 * Plugin URI: https://abdullahwp.com/automatic-email-obfuscation/
 * Description: Automatically detects and obfuscates visible email addresses and mailto links on the WordPress frontend.
 * Version: 1.1.0
 * Author: abdullahWp
 * Author URI: https://abdullahwp.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: automatic-email-obfuscation
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

/**
 * Start processing the final frontend HTML.
 */
add_action('template_redirect', 'aeo_start_output_buffer', 0);

function aeo_start_output_buffer(): void
{
    if (
        is_admin() ||
        wp_doing_ajax() ||
        wp_is_json_request() ||
        is_feed() ||
        is_robots() ||
        is_trackback()
    ) {
        return;
    }

    ob_start('aeo_process_frontend_html');
}

/**
 * Process the rendered page HTML.
 */
function aeo_process_frontend_html(string $html): string
{
    if (
        $html === '' ||
        stripos($html, '<html') === false ||
        stripos($html, '</body>') === false
    ) {
        return $html;
    }

    /**
     * Protect content that must never be modified.
     */
    $protected_blocks = [];

    $html = preg_replace_callback(
        '#<(script|style|textarea|pre|code|template|svg)\b[^>]*>.*?</\1>#is',
        function (array $matches) use (&$protected_blocks): string {
            $placeholder = '___AEO_PROTECTED_BLOCK_' . count($protected_blocks) . '___';
            $protected_blocks[$placeholder] = $matches[0];

            return $placeholder;
        },
        $html
    );

    /**
     * Process complete mailto anchor elements first.
     */
    $html = preg_replace_callback(
        '~<a\b([^>]*?)href\s*=\s*(["\'])mailto:([^"\']*)\2([^>]*)>(.*?)</a>~is',
        'aeo_process_mailto_anchor',
        $html
    );

    /**
     * Protect every HTML tag before processing visible text.
     *
     * This prevents replacement inside:
     * - href attributes
     * - data attributes
     * - Elementor attributes
     * - JSON stored in attributes
     * - CSS classes
     */
    $protected_tags = [];

    $html = preg_replace_callback(
        '/<!--.*?-->|<!DOCTYPE.*?>|<[^>]+>/is',
        function (array $matches) use (&$protected_tags): string {
            $placeholder = '___AEO_PROTECTED_TAG_' . count($protected_tags) . '___';
            $protected_tags[$placeholder] = $matches[0];

            return $placeholder;
        },
        $html
    );

    /**
     * Obfuscate visible plain-text email addresses.
     */
    $html = aeo_replace_visible_emails($html);

    /**
     * Restore HTML tags.
     */
    if (!empty($protected_tags)) {
        $html = strtr($html, $protected_tags);
    }

    /**
     * Restore protected blocks.
     */
    if (!empty($protected_blocks)) {
        $html = strtr($html, $protected_blocks);
    }

    /**
     * Inject the browser-side email reconstruction code.
     */
    $assets = aeo_get_frontend_assets();

    return preg_replace(
        '/<\/body>/i',
        $assets . '</body>',
        $html,
        1
    ) ?: $html;
}

/**
 * Process an existing mailto link.
 */
function aeo_process_mailto_anchor(array $matches): string
{
    $attributes_before = $matches[1];
    $quote             = $matches[2];
    $mailto_value      = $matches[3];
    $attributes_after  = $matches[4];
    $link_content      = $matches[5];

    $decoded_mailto = html_entity_decode(
        $mailto_value,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $mailto_parts = explode('?', $decoded_mailto, 2);

    $email = rawurldecode($mailto_parts[0]);
    $email = trim($email);
    $email = preg_replace('/\s+/', '', $email);

    $query = isset($mailto_parts[1])
        ? rawurldecode($mailto_parts[1])
        : '';

    if (!is_string($email) || !is_email($email)) {
        return $matches[0];
    }

    /**
     * Remove the original href attribute while preserving all other
     * Elementor classes, icon wrappers and accessibility attributes.
     */
    $attributes = trim($attributes_before . ' ' . $attributes_after);

    $attributes = preg_replace(
        '/\s*href\s*=\s*(["\']).*?\1/is',
        '',
        $attributes
    );

    $attributes = aeo_add_class_to_attributes(
        $attributes,
        'aeo-protected-email'
    );

    /**
     * Replace only the visible email text.
     *
     * Any icon or nested Elementor markup remains untouched.
     */
    $link_content = aeo_replace_email_inside_link_content(
        $link_content,
        $email
    );

    return sprintf(
        '<a %1$s href="#" data-aeo-email="%2$s" data-aeo-query="%3$s">%4$s</a>',
        trim($attributes),
        esc_attr(base64_encode($email)),
        esc_attr(base64_encode($query)),
        $link_content
    );
}

/**
 * Replace an email inside link content without removing nested icons or markup.
 */
function aeo_replace_email_inside_link_content(
    string $content,
    string $target_email
): string {
    $parts = preg_split(
        '/(<[^>]+>)/',
        $content,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );

    if (!is_array($parts)) {
        return $content;
    }

    foreach ($parts as $index => $part) {
        if ($part === '' || str_starts_with($part, '<')) {
            continue;
        }

        $parts[$index] = preg_replace_callback(
            aeo_email_pattern(),
            function (array $matches) use ($target_email): string {
                $found_email = html_entity_decode(
                    $matches[1],
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );

                if (strcasecmp($found_email, $target_email) !== 0) {
                    return $matches[0];
                }

                return '<span class="aeo-email-text"></span>';
            },
            $part
        );
    }

    $result = implode('', $parts);

    /**
     * Some Elementor mail links use custom text rather than displaying
     * the address. In that case, preserve the custom text as-is.
     */
    return $result;
}

/**
 * Replace plain-text emails outside HTML tags.
 */
function aeo_replace_visible_emails(string $text): string
{
    return preg_replace_callback(
        aeo_email_pattern(),
        function (array $matches): string {
            $email = html_entity_decode(
                $matches[1],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );

            if (!is_email($email)) {
                return $matches[0];
            }

            return sprintf(
                '<span class="aeo-protected-email aeo-email-inline" role="link" tabindex="0" data-aeo-email="%1$s"><span class="aeo-email-text"></span></span>',
                esc_attr(base64_encode($email))
            );
        },
        $text
    ) ?? $text;
}

/**
 * Reusable email-matching pattern.
 */
function aeo_email_pattern(): string
{
    return '/(?<![\w.%+\-])([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,63})(?![\w.\-])/i';
}

/**
 * Add a CSS class without creating duplicate class attributes.
 */
function aeo_add_class_to_attributes(
    string $attributes,
    string $class_name
): string {
    if (
        preg_match(
            '/\bclass\s*=\s*(["\'])(.*?)\1/is',
            $attributes,
            $class_match
        )
    ) {
        $classes = preg_split(
            '/\s+/',
            trim($class_match[2])
        );

        if (!is_array($classes)) {
            $classes = [];
        }

        if (!in_array($class_name, $classes, true)) {
            $classes[] = $class_name;
        }

        $new_class_attribute = sprintf(
            'class=%1$s%2$s%1$s',
            $class_match[1],
            esc_attr(implode(' ', array_filter($classes)))
        );

        return preg_replace(
            '/\bclass\s*=\s*(["\'])(.*?)\1/is',
            $new_class_attribute,
            $attributes,
            1
        ) ?? $attributes;
    }

    return trim($attributes) . ' class="' . esc_attr($class_name) . '"';
}

/**
 * JavaScript and CSS injected before the closing body tag.
 */
function aeo_get_frontend_assets(): string
{
    return <<<'AEO_ASSETS'
<style id="automatic-email-obfuscation-css">
.aeo-email-inline {
    cursor: pointer;
}

.aeo-protected-email,
.aeo-protected-email:hover,
.aeo-protected-email:focus,
.aeo-email-text {
    color: inherit;
    font: inherit;
    text-decoration: inherit;
}

.aeo-email-inline:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 2px;
}
</style>

<script id="automatic-email-obfuscation-js">
(function () {
    'use strict';

    function decodeValue(value) {
        if (!value) {
            return '';
        }

        try {
            return window.atob(value);
        } catch (error) {
            return '';
        }
    }

    function buildMailto(element) {
        var email = decodeValue(element.dataset.aeoEmail);
        var query = decodeValue(element.dataset.aeoQuery);

        if (!email) {
            return '';
        }

        return 'mailto:' + email + (query ? '?' + query : '');
    }

    function activateEmail(element) {
        var mailto = buildMailto(element);

        if (mailto) {
            window.location.href = mailto;
        }
    }

    function initialiseEmail(element) {
        if (!element || element.dataset.aeoReady === '1') {
            return;
        }

        var email = decodeValue(element.dataset.aeoEmail);

        if (!email) {
            return;
        }

        var emailTextElements = element.querySelectorAll('.aeo-email-text');

        emailTextElements.forEach(function (textElement) {
            textElement.textContent = email;
        });

        if (element.tagName.toLowerCase() === 'a') {
            element.setAttribute('href', buildMailto(element));
        } else {
            element.addEventListener('click', function () {
                activateEmail(element);
            });

            element.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activateEmail(element);
                }
            });
        }

        element.dataset.aeoReady = '1';
    }

    function initialiseAll(root) {
        var context = root || document;

        if (
            context.nodeType === 1 &&
            context.matches &&
            context.matches('[data-aeo-email]')
        ) {
            initialiseEmail(context);
        }

        if (!context.querySelectorAll) {
            return;
        }

        context
            .querySelectorAll('[data-aeo-email]')
            .forEach(initialiseEmail);
    }

    function startEmailProtection() {
        initialiseAll(document);

        if (!document.body) {
            return;
        }

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) {
                        initialiseAll(node);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            startEmailProtection
        );
    } else {
        startEmailProtection();
    }
})();
</script>
AEO_ASSETS;
}
