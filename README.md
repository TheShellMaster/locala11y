# LocalA11y — Local WCAG 2.2 & EAA Accessibility Audit for WordPress

A lightweight, 100% local WordPress accessibility auditing plugin powered by bundled `axe-core` (MPL-2.0). Designed for **WCAG 2.2 Level AA** and **European Accessibility Act (EAA / EU Directive 2019/882)** remediation — with **zero external SaaS**, **zero tracking**, and **zero fake overlays**.

## Features
- **Local WCAG 2.2 AA Scanner**: Audits published pages/posts in Desktop (1280px) or Mobile (390px) viewports directly in your browser.
- **Admin-Bar Quick Audit Drawer**: One-click front-end page audit and DOM element highlighting for logged-in administrators (zero bytes loaded for visitors).
- **Findings Explorer**: Filter by severity and status, inspect CSS selectors/HTML snippets, export formula-safe CSV, or print reports.
- **EAA Accessibility Statement Generator**: English & French templates with `[locala11y_statement]` shortcode, optional auto-imported known limitations, and one-click page publishing.
- **Manual WCAG 2.2 Checklist**: Tracks keyboard, screen reader, zoom reflow, captions, target size, and authentication checks.

## Automated CI & WordPress.org Publishing
- **Code & Guidelines Check**: `.github/workflows/plugin-check.yml` runs `php -l` and official `WordPress/plugin-check-action` on every push.
- **Automated Release & WordPress.org SVN Deploy**: `.github/workflows/deploy-wporg.yml` builds a clean ZIP and deploys to WordPress.org via `10up/action-wordpress-plugin-deploy` on `v*` Git tags once `SVN_USERNAME` and `SVN_PASSWORD` secrets are configured.

## License
- Plugin code: GPL-2.0-or-later (`LICENSE`)
- Bundled `axe-core` 4.10.3: MPL-2.0 (`licenses/axe-core-MPL-2.0.txt`)
