# Installation and upgrades

## Requirements

WordPress 6.7+, WooCommerce 9.6+, 64-bit PHP 8.2+, PHP OpenSSL, and MySQL-compatible InnoDB transaction support. Use BDT and two currency decimals. Set WordPress Settings > General > Timezone to Asia/Dhaka for the intended calendar. The plugin follows the configured site timezone; it does not silently change it.

Install through Plugins > Add New > Upload Plugin using the installable ZIP. Activate WooCommerce first, then this plugin. Per-site activation only; do not network activate. No build step is needed.

## First-run checklist

1. Open Ghoroa Hat Rewards > Settings. Review module flags and General settings.
2. Select the actual inaugural package product under Trust Wallet. An arbitrary order totaling ৳999 never grants the bonus. Check product price and exclusions in WooCommerce.
3. Review automatic Membership, Refer, Leaderboard and Winners page mappings. Existing page content is preserved. Endpoint slugs must be unique and avoid WooCommerce reserved slugs. Account URLs follow WooCommerce's configured account page.
4. Review thresholds, referral consent/attribution, completion statuses, expiry, refund restoration and single-wallet checkout policy. Save settings using the native WordPress form.
5. Configure site mail delivery. For SMS, enter a public HTTPS JSON endpoint, field mappings and write-only bearer credential. Disabled is the default. Use a provider with an idempotency-key contract.
6. Create reward definitions and six monthly campaigns (top-prize and Magic Box for each category); ensure stock and campaign quota are sufficient before publication.
7. Review editable notification templates and privacy notices. Obtain tracking consent and public winner consent as appropriate.
8. Open System Health: verify schema, engine, BDT, job scheduling and queues. Use WooCommerce > Status > Scheduled Actions to inspect group `ghr`.
9. Test enrollment, verification, completed purchase, credit apply/remove, COD, your actual paid gateway, refunds and theme on staging. The supplied test report identifies checks already performed.

Automatic pages are created only when missing. Route flushing occurs on activation/deactivation or necessary slug/page migrations. If a third-party permalink tool changes routes, save WordPress Permalinks once after resolving conflicts.

## Jobs and delivery

WooCommerce supplies Action Scheduler. Install a real system cron invoking WordPress cron regularly on low-traffic stores. Maintenance is queued every minute and monthly preparation hourly. Financial hooks queue order reconciliation. Reactivation queues bounded recovery batches. Monitor failed scheduled actions and notification failures; retry only after correcting the cause.

Email uses `wp_mail()`: configure authenticated delivery through your usual WordPress mail infrastructure. Provider acceptance is distinct from inbox delivery. Never run the supplied fixtures on a live store.

## Upgrade and recovery

Back up database and files together. Test the new ZIP on a staging restore; replace the plugin folder, reactivate and inspect System Health. Versioned migrations use `dbDelta()` and check table engines. This initial release uses schema version 1. Do not downgrade across a future schema change without restoring the matching full backup.

Deactivation preserves data and removes plugin jobs/routes. Activation preserves members, IDs, balances and existing pages. Ordinary uninstall preserves records by default. Explicit removal requires a recent privileged preview, exact confirmation and `BACKUP VERIFIED`, then the same administrator must uninstall within the timed window. WordPress users/orders are preserved. The permanent member-ID sequence remains to prevent reuse; a new ledger epoch separates a reinstalled financial program from old order metadata.

Retain WordPress authentication salts with backups: changing salts invalidates attribution cookies and encrypted queued verification/SMS credentials; re-enter credentials and request fresh codes after a salt rotation.
