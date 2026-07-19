# Automatic Email Obfuscation

Automatic Email Obfuscation is a lightweight WordPress plugin that protects visible email addresses and `mailto:` links from basic harvesting bots while keeping them usable for visitors.

It processes the final front-end HTML, replaces email addresses with encoded data, and reconstructs them in the browser. Scripts, styles, code samples, SVG markup, form fields, and HTML attributes are protected from accidental replacement.

## Features

- Automatically detects visible email addresses.
- Protects existing `mailto:` links, including subject and body parameters.
- Preserves nested icons and Elementor markup inside links.
- Avoids admin, AJAX, REST, feed, robots, and trackback responses.
- Does not require a settings screen or external service.
- Uses no third-party JavaScript library.

## Requirements

- WordPress 6.0 or newer.
- PHP 7.4 or newer.
- A theme that outputs a normal closing `</body>` tag.

## Installation

1. Download the latest release ZIP.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP and activate **Automatic Email Obfuscation**.
4. Clear any page or CDN cache.

No configuration is required.

## How it works

The plugin starts an output buffer for normal front-end HTML responses. Before replacing email addresses, it temporarily protects blocks such as `script`, `style`, `textarea`, `pre`, `code`, `template`, and `svg`. Email data is reconstructed by a small browser-side script after the page loads.

This reduces exposure to simple source-code scrapers. It is not a guarantee against determined bots or browser automation.

## Compatibility and caching

The plugin is designed to work with page builders and full-page caches. After activation or an update, purge all WordPress, server, and CDN caches. Test forms and any pages containing unusual HTML before deploying to production.

## Development

Run a PHP syntax check with:

```text
php -l automatic-email-obfuscation.php
```

Bug reports and focused pull requests are welcome.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
