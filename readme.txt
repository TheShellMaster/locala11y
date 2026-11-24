=== LocalA11y — Local WCAG 2.2 & EAA Accessibility Audit ===
Contributors: theshellmaster
Tags: accessibility, wcag, eaa, accessibility-audit, accessibility-statement
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run 100% local WCAG 2.2 & EAA accessibility audits with bundled axe-core, track manual checks, and generate an EAA Accessibility Statement. No overlays.

== Description ==

**LocalA11y** helps website owners, agencies, and content teams audit WordPress sites for **WCAG 2.2 Level AA** and **European Accessibility Act (EAA / EU Directive 2019/882)** readiness — completely locally inside WordPress and your browser.

No external SaaS subscription, no cloud account, no third-party tracking, and **no deceptive front-end accessibility overlay widgets**.

= Key Features =

* **100% Local Browser Auditing (`axe-core`)**: Scans published pages and posts in Desktop (1280px) or Mobile (390px) viewports against WCAG 2.0, 2.1, and **WCAG 2.2 Level AA** rules using the bundled `axe-core` engine.
* **Admin-Bar Quick Page Audit**: Logged-in administrators can audit any front-end page in one click, inspect violations in an accessible side drawer, and highlight offending elements directly on the page. **Zero bytes are ever loaded for public visitors.**
* **Findings Explorer & Remediation Tracking**: Filter issues by severity (`Critical`, `Serious`, `Moderate`, `Minor`) and status (`Open`, `Reviewed`, `Fixed`), inspect exact CSS selectors and HTML snippets, and follow official WCAG guidance links.
* **EAA / WCAG 2.2 Accessibility Statement Generator**: Configure and publish a transparent Accessibility Statement in **English or French** using the `[locala11y_statement]` shortcode or one-click page publishing — optionally auto-listing open findings in your Known Limitations section.
* **Manual EAA Testing Checklist**: Track the essential WCAG 2.2 criteria that automated scanners cannot fully verify (keyboard-only navigation, focus visibility, screen reader walkthrough, captions, 200% zoom reflow, target size, accessible authentication).
* **CSV & Printable Reports**: Export scan results to CSV (formula-safe) or print/save clean audit summaries for your team or clients.

= Honest Accessibility Notice =

Automated accessibility scanners typically detect 30% to 50% of WCAG criteria. **LocalA11y does not legally certify WCAG or EAA compliance and does not inject automated "fixes" or overlays.** Real accessibility requires fixing issues in your theme, plugins, and content alongside manual keyboard and screen reader testing.

= Privacy & Data Storage =

* All scan records, findings, and statement settings are stored exclusively in your own WordPress database.
* LocalA11y does not send page content, URLs, or scan results to any external server and does not load scripts from external CDNs.
* Deactivating the plugin preserves your scan history. Deleting the plugin cleanly removes its custom tables and options.

= Third-Party Code =

This plugin bundles **axe-core 4.10.3** by Deque Systems, Inc., licensed under the **Mozilla Public License 2.0 (MPL-2.0)**, which is GPL-compatible.

* Bundled file: `assets/vendor/axe-core/axe.min.js`
* License text: `licenses/axe-core-MPL-2.0.txt`
* Human-readable source: https://github.com/dequelabs/axe-core/tree/v4.10.3 and https://cdn.jsdelivr.net/npm/axe-core@4.10.3/axe.js

== Installation ==

1. Upload the `locala11y` folder to `/wp-content/plugins/`, or install the plugin via **Plugins → Add New Plugin → Upload Plugin**.
2. Activate **LocalA11y** from the Plugins screen.
3. Open **LocalA11y → Dashboard & Scanner** in your WordPress admin menu.
4. Select your published pages and click **Start Local Audit**. Keep the admin tab open while your browser scans each page locally.
5. Review issues in **Findings Explorer**, work through the **Manual Checklist**, and publish your statement under **EAA Statement**.

== Frequently Asked Questions ==

= Does LocalA11y add a floating accessibility widget or overlay to my site? =

No. Accessibility overlays do not fix underlying code barriers and are widely discouraged by accessibility professionals and European regulators. LocalA11y loads zero scripts or styles for public visitors.

= Does this plugin require an external account or API key? =

No. Everything runs locally in your WordPress admin and browser using the bundled `axe-core` library.

= Does LocalA11y guarantee legal compliance with the EAA or ADA? =

No automated tool can guarantee full compliance. LocalA11y gives you transparent automated evidence, a structured manual testing checklist, and a customizable Accessibility Statement so you can remediate real barriers honestly.

= What happens when I uninstall LocalA11y? =

Deactivating the plugin keeps your data safe. Deleting the plugin completely removes the `locala11y_scans` and `locala11y_findings` database tables and all `locala11y_*` options.

== Screenshots ==

1. LocalA11y Dashboard with severity KPIs and local WCAG 2.2 AA batch scanner.
2. Findings Explorer with severity badges, CSS selectors, HTML snippets, and CSV export.
3. EAA / WCAG 2.2 Accessibility Statement Generator with live preview and one-click publishing.
4. Built-in Manual WCAG 2.2 & EAA Verification Checklist.

== Changelog ==

= 1.0.0 =
* Initial release.
* Local browser-based WCAG 2.2 AA & EAA scanner powered by bundled axe-core 4.10.3.
* Admin-bar quick page audit drawer with on-page element highlighting (admin-only).
* Findings Explorer with status tracking, CSV export, and printable report view.
* EAA / WCAG 2.2 Accessibility Statement generator (English & French) with `[locala11y_statement]` shortcode and one-click page publishing.
* Interactive Manual EAA Testing Checklist.

== Upgrade Notice ==

= 1.0.0 =
Initial release of LocalA11y.
