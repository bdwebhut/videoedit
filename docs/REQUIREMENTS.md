# Requirement coverage and supported scope

The original full request is preserved in ORIGINAL-REQUIREMENTS.md. The reviewed technical design is in APPROVED-DESIGN.md. The implementation follows its safe initial financial policies; operational acceptance still applies before live use.

| Requirement sections | Implementation | Verification / guide |
|---|---|---|
| 1–2 Identity and membership | Membership, Reconciliation, permanent user IDs and GH- IDs, verification and states | integration, advanced, reconciliation; administrator guide |
| 3 WooCommerce | Orders, Calculator, separate reward services; WC CRUD, HPOS, simple/variation, cumulative refunds | two-platform suite matrix, checkout/lifecycle suites |
| 4 Pages/routes | Installation and Frontend; missing pages, configurable six endpoints, preserved menus | migrations/reactivation and browser direct endpoint loads |
| 5 Membership dashboard | Owner card, metrics, monthly activity, referral/reward/notification summaries | shortcode and browser checks |
| 6 Trust | Lifetime selected-product entitlement, separate ledger, history/filters/expiry, dropdown, checkout | integration, checkout, expiry, lifecycle |
| 7 Referral | Consent-based first-party attribution, approval/hold/review, private stats and wallet | referrals, advanced, competition |
| 8 Seeds | Personal-only whole-unit earning, limits/progress/separate ledger | integration, checkout, lifecycle |
| 9 Checkout | Native ex-tax fixed-cart coupon credit; classic and Store API block controls; reserve/commit/release | native cart tests and both actual COD browser orders; concurrency |
| 10 Competition | SQL monthly scores, published formulas, deterministic six-slot selection, immutable reviewed/published versions | competition, synthetic performance |
| 11 Prizes | Predefined server assignments, owner lifecycle, six types and native coupons | integration, competition, lifecycle |
| 12 Notifications | Account history, preferences, editable templates, queued mail/optional configurable SMS | advanced and mocked SMS; production transport acceptance outstanding |
| 13–14 Administration/offline | Capability-gated screens, profiles/search/notes, adjustments, native order attribution, reports | permissions, native admin orders, browser settings/screens |
| 15 Ledger/schema | 24 indexed InnoDB tables, integer arithmetic, locks, lots and idempotent immutable postings | schema/engines, projections, real concurrent workers |
| 16 UI | Bengali-first scoped mobile components, dropdowns, reveal/reduced-motion, shortcode library | shortcode/XSS suite, mobile overflow and automated axe |
| 17 Privacy/security | Authenticated nonce/ownership/capabilities, sanitized input, escaped output, prepared SQL, rate limits, privacy tools | automated permissions/escaping/fraud tests; independent security audit not claimed |
| 18 Jobs | WooCommerce Action Scheduler, bounded queues/recovery/cleanup | retries, native worker and reactivation |
| 19 Settings | Validated general/wallet/referral/competition/notification/checkout settings and System Health | native settings browser save and domain tests |
| 20 Architecture | Separate OOP services, shared financial rules and documented filters | architecture/API/extensions guides, WordPress-Core lint |
| 21 Lifecycle | Migration, first-run checklist/settings, safe pages/jobs and preservation by default | schema migration and native deactivate/reactivate checks |
| 22 Testing | 11 repeatable suites, concurrency/browser/lifecycle/performance tools | TEST-RESULTS.md and raw evidence |
| 23 Delivery | Installable ZIP, readable source, schema, docs, tests and changelog | release archive manifest |

## Deliberate initial-release boundaries

- BDT with two currency decimals and 64-bit PHP; no multicurrency ledger conversion.
- One wallet per order. Settings reject unsupported stacking combinations; independent balances remain separate.
- Reward base excludes shipping/tax. Native coupon taxation can reduce tax due in the normal WooCommerce way.
- Reviewed results use deterministic ranking; no random or gambling outcome logic.
- First-run setup is a guided checklist with native settings, not a separate account/authentication system.
- Individual-site activation; network activation is unsupported.
- Financial history is preserved by default, including after ordinary uninstall. Explicit removal is separately privileged, timed and confirmed.
- Existing WordPress/WooCommerce authentication, payment processing and stock remain authoritative.

## Deployment acceptance

Configure the actual inaugural product, category exclusions, real mail/SMS transport, prize definitions/campaigns, privacy consent policy and reliable cron. Run the intended paid gateway sandbox and actual theme/page-builder/POS combinations on staging. The release candidate includes test evidence; untested external integrations must not be inferred from compatibility declarations.
