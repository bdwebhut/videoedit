# Account design refresh — 1.0.0-rc.2

The plugin components now follow the supplied account-theme reference: white cards, light gray borders, small green icon tiles, compact typography, dark headings and restrained green actions. The existing theme retains control of its header, welcome banner, sidebar and account content container.

The membership dashboard uses a compact profile with a status badge, three separate wallet cards, summary metrics and responsive panels for monthly activity, referral sharing, verification, competition, rewards and notifications. Transaction tables, inputs, dropdowns, progress, reward states and preference forms use the same visual language. Bengali labels replace raw field names in common customer tables and preferences.

CSS stays within `.ghr` components and inherits the theme font. No global WooCommerce sidebar/header styling or external font framework is introduced. Financial services, database schema and reward rules are unchanged. Updating from rc.1 does not need a new schema migration.

## Previews

These screenshots show actual plugin rendering on the local test theme, cropped to the plugin content. The supplied production theme is a visual reference; its source files were not available for direct testing.

- [Membership desktop](test-evidence/design-refresh/gh-membership-desktop.png)
- [Membership mobile](test-evidence/design-refresh/membership-mobile.png)
- [Trust Wallet](test-evidence/design-refresh/gh-trust-wallet-desktop.png)
- [Referrals](test-evidence/design-refresh/gh-referrals-desktop.png)
- [Trust Seeds](test-evidence/design-refresh/gh-trust-seeds-desktop.png)
- [Rewards](test-evidence/design-refresh/gh-rewards-desktop.png)
- [Notifications](test-evidence/design-refresh/gh-notifications-desktop.png)

## Validation

- All 22 shortcode components and owner/anonymous output checks: 69 assertions pass on WordPress 7.1.3/WooCommerce 11.2.1 and WordPress 6.7/WooCommerce 9.6.2.
- Real browser classic and Blocks COD checkout passes with the revised CSS and markup.
- All six account endpoints load; native settings save and management-screen smoke checks pass with no JavaScript page errors.
- WordPress-Core PHPCS has zero errors/warnings; modified PHP passes syntax checking.
- All six account tabs passed automated axe accessibility checks with zero violations. Each tab passed document-overflow checks at 320, 390 and 768px widths (18 responsive checks). Results are in the accompanying UI audit JSON.

Install the rc.2 ZIP using WordPress Plugins > Add New > Upload Plugin, then choose replacement of the installed version. Clear theme/page/CDN caches after updating so the versioned stylesheet is refreshed. Review the components within the actual theme on staging before production deployment.
