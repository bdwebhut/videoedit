=== Ghoroa Hat Smart Membership & Rewards ===
Contributors: bdwebhut
Tags: woocommerce, membership, rewards, referral, loyalty
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 1.0.0-rc.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bengali-first membership, three independent reward wallets, referrals, monthly awards and notifications for WooCommerce.

== Description ==
Standalone plugin for Ghoroa Hat, BD WEBHUT PRIVATE LIMITED. Keeps existing WordPress/WooCommerce customer authentication and user IDs. Uses native WooCommerce orders, coupons, Action Scheduler, HPOS and classic/Blocks checkout integrations.

This is a release candidate. Read docs/INSTALLATION.md and docs/TEST-RESULTS.md before deployment. Initial financial support is BDT with two decimals, 64-bit PHP, InnoDB and WooCommerce 9.6 or later. Multisite requires individual site activation; network activation is not supported.

== Installation ==
1. Back up the database and files, and prepare a staging copy.
2. Activate WooCommerce, then upload this ZIP in Plugins > Add New > Upload Plugin.
3. Activate and open Ghoroa Hat Rewards > Settings; complete the first-run checklist.
4. Select the eligible inaugural product, configure pages, notification delivery and competition prizes.
5. Verify System Health and complete your gateway/theme staging acceptance checks.

== Frequently Asked Questions ==
= Are funds transferable or withdrawable? =
No. Benefits are product credits; three wallets remain independent. One wallet per order is supported.

= Does deleting the plugin remove accounting records? =
No, records are preserved by default. System Health provides a separate privileged, timed, typed-confirmation removal process.

= Which order status earns rewards? =
Completed by default. Earlier qualifying statuses are configurable, with financial risk explained in Settings.

= Is SMS included? =
A configurable HTTPS JSON gateway and adapter filter are included. You supply a provider and credentials; SMS is disabled until configured.

== Changelog ==
= 1.0.0-rc.1 =
Initial release candidate; see CHANGELOG.md and docs/TEST-RESULTS.md.
