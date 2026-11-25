=== LocalA11y ===
Contributors: theshellmaster
Tags: accessibility, wcag, eaa, accessibility-audit, accessibility-statement
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Assist with WCAG 2.2 and EAA accessibility audits locally in WordPress using bundled axe-core, manual checklists, and statement templates.

== Description ==

**LocalA11y** assists website owners, agencies, and content teams in auditing WordPress sites for **WCAG 2.2 Level AA** and **European Accessibility Act (EAA / EU Directive 2019/882)** readiness — completely locally inside WordPress and your browser.

No external SaaS subscription, no cloud account, no third-party tracking, and **no deceptive front-end accessibility overlay widgets**.

= Key Features =

* **100% Local Browser Auditing (`axe-core`)**: Scans published pages, posts, custom post types, or any custom same-site URL (such as WooCommerce shop, checkout, or archive pages) in Desktop (1280px) or Mobile (390px) viewports against WCAG 2.0, 2.1, and **WCAG 2.2 Level AA** rules using the bundled `axe-core` engine.
* **Admin-Bar Quick Page Audit**: Logged-in administrators can audit any front-end page in one click, inspect violations and manual review items in an accessible side drawer, and highlight offending elements directly on the page. **Zero bytes are ever loaded for public visitors.**
* **Findings Explorer & Remediation Tracking**: Filter issues by severity (`Critical`, `Serious`, `Moderate`, `Minor`) and status (`Open`, `Reviewed`, `Fixed`), copy CSS selectors in one click, inspect HTML snippets, and follow WCAG remediation guides.
* **EAA / WCAG 2.2 Accessibility Statement Assistant**: Configure and publish a transparent self-assessment Accessibility Statement in **English or French** using the `[locala11y_statement]` shortcode or one-click page publishing — optionally auto-listing open findings in your Known Limitations section.
* **Manual EAA Testing Checklist**: Track the essential WCAG 2.2 criteria that automated scanners cannot verify on their own (keyboard-only navigation, focus visibility, screen reader walkthrough, captions, 200% zoom reflow, target size, accessible authentication).
* **CSV & Printable Reports**: Export scan results to Excel-friendly UTF-8 CSV (formula-safe) or print/save clean audit summaries for your team or clients.

= Honest Accessibility & Legal Disclaimer =

**No plugin can provide, automate, or guarantee 100% legal compliance** with WCAG, the European Accessibility Act (EAA), RGAA, the ADA, or any other law or regulation. Automated accessibility scanners typically detect 30% to 50% of technical barriers and cannot replace human testing.

**LocalA11y** is designed to **assist** site administrators with local technical diagnostics, structured manual verification checklists, and self-assessment statement templates. It does not inject automated "fixes" or overlays, and nothing in this plugin constitutes legal certification or legal advice. Ultimate responsibility for accessibility and regulatory conformance remains with the website operator.

= Privacy, Data Storage & External Links =

* All scan records, findings, checklist items, and statement settings are stored exclusively in your own WordPress database.
* LocalA11y never sends page content, URLs, telemetry, or scan results to external servers and never loads scripts from external CDNs.
* Remediation guidance links displayed inside the admin interface open external documentation (such as Deque University or W3C) in a new browser tab **only when clicked by an administrator**.
* No front-end credits or external links are ever embedded on your public website.
* Deactivating the plugin preserves your scan history. Deleting the plugin cleanly removes its custom database tables and options.

= Source Code & Third-Party Libraries =

* **Plugin Source Repository**: All LocalA11y PHP, JavaScript, and CSS files are distributed in human-readable, unminified form and maintained publicly at https://github.com/TheShellMaster/locala11y
* **Third-Party Engine**: This plugin bundles **axe-core 4.14.1** by Deque Systems, Inc., licensed under the **Mozilla Public License 2.0 (MPL-2.0)**, which is compatible with the GNU GPL.
  * Bundled file: `assets/vendor/axe-core/axe.min.js`
  * License text: `licenses/axe-core-MPL-2.0.txt`
  * Human-readable source: https://github.com/dequelabs/axe-core/tree/v4.14.1 and https://cdn.jsdelivr.net/npm/axe-core@4.14.1/axe.js

== Installation ==

1. Upload the `locala11y` folder to `/wp-content/plugins/`, or install the plugin via **Plugins → Add New Plugin → Upload Plugin**.
2. Activate **LocalA11y** from the Plugins screen.
3. Open **LocalA11y → Dashboard & Scanner** in your WordPress admin menu.
4. Select your published pages (or add custom same-site URLs) and click **Start Local Audit**. Keep the admin tab open while your browser scans each page locally.
5. Review issues in **Findings Explorer**, work through the **Manual Checklist**, and draft your self-assessment statement under **EAA Statement**.

== Frequently Asked Questions ==

= Does LocalA11y make my website 100% EAA or ADA compliant? =

No. No plugin can provide 100% legal compliance. LocalA11y assists you in finding technical WCAG barriers locally, tracking manual accessibility checks, and publishing a transparent self-assessment statement so you can remediate issues in your theme and content.

= Does LocalA11y add a floating accessibility widget or overlay to my site? =

No. Accessibility overlays do not fix underlying code barriers and are discouraged by accessibility specialists and European regulators. LocalA11y loads zero scripts or styles for public visitors.

= Does this plugin require an external account or API key? =

No. Everything runs locally in your WordPress admin and browser using the bundled `axe-core` library.

= What happens when I uninstall LocalA11y? =

Deactivating the plugin keeps your data safe. Deleting the plugin completely removes the `locala11y_scans` and `locala11y_findings` database tables and all `locala11y_*` options.

== Screenshots ==

1. LocalA11y Dashboard with severity KPIs, custom URL input, and local WCAG 2.2 AA batch scanner.
2. Findings Explorer with severity badges, one-click CSS selector copy, HTML snippets, and CSV export.
3. EAA / WCAG 2.2 Accessibility Statement Assistant with live preview and one-click publishing.
4. Built-in Manual WCAG 2.2 & EAA Verification Checklist.

== Changelog ==

= 1.0.0 =
* Initial release.
* Local browser-based WCAG 2.2 AA & EAA audit assistant powered by bundled axe-core 4.14.1.
* Admin-bar quick page audit drawer with keyboard navigation, remediation details, and on-page element highlighting (admin-only).
* Findings Explorer with status tracking, one-click selector copy, Excel-friendly UTF-8 CSV export, and printable report view.
* EAA / WCAG 2.2 Accessibility Statement template assistant (English & French) with `[locala11y_statement]` shortcode and one-click page publishing.
* Interactive Manual EAA Testing Checklist.

== Upgrade Notice ==

= 1.0.0 =
Initial release of LocalA11y.
