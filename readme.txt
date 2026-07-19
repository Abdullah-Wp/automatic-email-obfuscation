=== Automatic Email Obfuscation ===
Contributors: abdullahwp
Tags: email obfuscation, anti spam, mailto, privacy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically obfuscates visible email addresses and mailto links on the WordPress frontend.

== Description ==

Automatic Email Obfuscation protects visible email addresses and mailto links from basic harvesting bots while preserving the experience for visitors.

The plugin requires no configuration and does not connect to any external service. Scripts, styles, code samples, SVG markup, form fields, and HTML attributes are protected from accidental replacement.

Obfuscation reduces exposure to simple source-code scrapers, but it cannot guarantee protection against determined bots or browser automation.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate Automatic Email Obfuscation.
3. Clear any page, server, and CDN caches.
4. Test pages containing email addresses and forms.

== Changelog ==

= 1.1.0 =
* Improved protection of HTML elements, attributes, and mailto links.
* Preserved nested markup in existing email links.

= 1.0.0 =
* Initial release.
