# Current release: 1.0.0-rc.2

The account design refresh has separate validation recorded in [DESIGN-REFRESH.md](DESIGN-REFRESH.md). The results below are the full 1.0.0-rc.1 financial and compatibility baseline; financial services and schema are unchanged by rc.2.

# Test results — 1.0.0-rc.1

Executed 2026-10-16 in isolated local Docker test stores. These are observed test results, not production certification.

## Compatibility matrix

| Environment | WordPress | WooCommerce | PHP | Database | Order storage | Assertions |
|---|---|---|---|---|---|---|
| Current | 7.1.3 | 11.2.1 | 8.2.34 | 11.4.13-MariaDB-ubu2404 | HPOS enabled | 497 passed |
| Minimum | 6.7 | 9.6.2 | 8.2.34 | 11.4.13-MariaDB-ubu2404 | Legacy WooCommerce CRUD | 497 passed |

All 11 suites passed on each environment with no PHP warning, fatal error or WordPress database error detected. Suite breakdown:

| Suite | Assertions per environment |
|---|---|
| unit | 214 |
| integration | 98 |
| checkout | 20 |
| advanced | 22 |
| referrals | 14 |
| expiry | 9 |
| competition | 15 |
| reconciliation | 16 |
| shortcodes | 69 |
| sms | 7 |
| lifecycle | 13 |

## Additional observed checks

- Real independent concurrent database workers attempted two ৳60 reservations against ৳100. Exactly one succeeded; the other rejected insufficient available credit.
- Fresh packaged ZIP installation through native WP-CLI succeeded on a separate clean WordPress 7.1.3/WooCommerce 11.2.1 site: 24 custom tables, four pages and 22 registered shortcodes. Ordinary uninstall preserved membership/financial records; ZIP reinstall retained page mappings and ID sequence. Separately armed explicit removal was exercised only on that disposable copy and preserved WordPress users, native WooCommerce orders and the permanent ID sequence.
- Native WordPress deactivation/reactivation on both environments preserved member count, ledger count, page mappings and permanent ID sequence. WooCommerce shared Action Scheduler migration was completed using its native CLI; the real worker executed a queued plugin score job and its stored state was verified complete.
- Both actual browser COD checkouts completed: classic and Checkout Blocks reserved ৳100, accepted the order and loaded the WooCommerce thank-you page.
- Browser checks visited all six account endpoints, the management screens listed in the raw results, and saved validated settings through the native nonce-protected WordPress form. No JavaScript page errors occurred.
- Automated axe analysis of the membership portal found no violations. At 390px viewport width no horizontal document overflow occurred. Desktop/mobile screenshots are included in source evidence.
- PHP syntax checks passed for every PHP source/test file; Node syntax checks passed for both JavaScript assets.
- WordPress-Core PHPCS: zero errors and zero warnings after documented per-statement exceptions for reviewed trusted SQL identifiers, prepared predicates and allowlisted score recipes. No global SQL-security sniff exclusion is configured. This result does not claim full WordPress-Docs/Extra compliance or an independent penetration test.
- Monthly SQL aggregation of 10,000 synthetic members and 10,000 synthetic order-effect records produced 10,000 scores and a bounded 100-row candidate result. Local measured times were 0.2473s (current) and 0.2209s (minimum), with zero additional allocated PHP heap pages at the measurement boundary. Synthetic records were rolled back. This is not end-to-end real-store load capacity or a guarantee for another server.

## Coverage

Tests exercise existing/new customer enrollment, verified activation, email ownership/reverification, duplicate verified phones, inaugural/repeated bonus, native variable products, payment-success versus completion, referrals/consent/tamper/self/guest/repeat policy, commission approval, personal Seeds, thresholds and exclusions, exact inclusive/exclusive-tax checkout credit, reservation idempotency, cancellation/retry/late settlement, partial/full refunds, source expiry/debt provenance, monthly ties/six-slot publication/corrections, server prize ownership and coupon identity, message deduplication/retry bounds, identity reconciliation and historical WooCommerce order access, capabilities/nonces, shortcode output and XSS escaping, migrations and projections.

## Remaining deployment acceptance

Real online payment gateway callbacks were not exercised against a provider sandbox; payment success was tested through native WooCommerce `payment_complete()` and COD browser flows. Email transports and SMS payloads/retry logic were tested with mocks/local fixtures; real inbox/SMS delivery and provider contracts remain deployment checks. Elementor/Bricks and third-party POS/theme/plugin combinations, manual screen-reader testing, other SQL engine/version combinations and production traffic were not certified. SMTP cannot guarantee exactly-once inbox delivery after an ambiguous crash. Use staging acceptance and configure mail/SMS, products, prizes, consent and cron before live use.

Raw machine-readable suite, browser and PHPCS reports, plus concurrency/performance/reactivation logs, are in `test-evidence`. Screenshots show synthetic test data only. All supplied test credentials are isolated fixture defaults and must never be used for deployment.
