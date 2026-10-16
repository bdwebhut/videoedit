# Ghoroa Hat Smart Membership & Rewards — design for approval

Status: proposed design; implementation has not started.

This document accompanies the unmodified [requirements](requirements.md). It is not a replacement for them. All 23 requirement sections remain in scope. Approval of this document authorizes implementation of the defaults below; any changes to those defaults should be stated before approval.

## 1. Delivery and compatibility

- Standalone plugin: `ghoroa-hat-rewards`, PHP namespace `GhoroaHat\Rewards`, global hooks/options/functions/table suffixes prefixed `ghr_`, text domain `ghoroa-hat-rewards`.
- Proposed minimums: PHP 8.2, WordPress 6.7, WooCommerce 9.6; test the minimum combination and current stable releases available at implementation time. Confirm the supported Store API and Blocks extension contracts against those versions before registering compatibility.
- BDT store currency required for the initial financial implementation. On other currencies, show diagnostics and disable earning/redemption rather than assuming exchange rates. Multicurrency extensions require a separately tested adapter.
- InnoDB transactional tables required. Activation and health checks must detect unavailable transactional guarantees and disable financial writes with an actionable error.
- HPOS and legacy order storage tested through WooCommerce CRUD exclusively. No direct orders SQL and no changes to payment processing, inventory, login identity, or account ownership.
- Use the isolated workspace directly; no connection to the abandoned video-editing project and no changes to its checkout.
- Final deliverables: readable source, installable ZIP, migration/schema reference, all shortcodes, admin tools, REST documentation, installation/upgrade guide, user/admin guides, extension guide, changelog, automated results and a manual verification checklist.

## 2. Module architecture and interfaces

```text
ghoroa-hat-rewards/
  ghoroa-hat-rewards.php        plugin metadata and bootstrap only
  uninstall.php               preserve data unless explicit removal was authorized
  src/
    Infrastructure/           container, repositories, transactions, locks, clock
    Installation/             compatibility, versioned migrations, pages, capabilities
    Settings/                 typed defaults, validation, sections, setup wizard
    Membership/               enrollment, activation, verified identities, reconciliation
    Finance/                  Money, calculator, immutable ledger, holds, reservations
    TrustBonus/               selected-product eligibility and lifetime award entitlement
    Referral/                 URL tracking, attribution, fraud checks, conversions, approval
    TrustSeeds/               personal purchase earning and redemption policy
    Checkout/                 native discount adapter, classic/Blocks controllers, order saga
    Leaderboard/              monthly aggregation, rankings, snapshots, publication
    Rewards/                  definitions, campaigns, assignments, claims, fulfillment
    Notifications/            templates, preferences, outbox, email/SMS adapters, retries
    Admin/                    capability-controlled controllers, tables, reports
    Frontend/                 account routes, shortcodes, view models, asset loader
    Rest/                     authenticated commands and public aggregate reads
    Jobs/                     Action Scheduler jobs and recovery
    Privacy/                  consent, retention, export, erasure restrictions
    Audit/                    append-only administrative and financial event records
  templates/{account,components,public,admin}/
  assets/{css,js}/             scoped styles, vanilla JS, supported Blocks integration
  languages/
  tests/{unit,integration,e2e}/
  docs/
```

Stable interfaces:

- `MembershipService::enroll(userId)`, `activate(userId, orderId)`, `changeStatus(...)`.
- `RewardCalculator::calculate(orderOrCartContext)` returns eligible product amounts, item allocations, redeemed-credit deductions and integer reward amounts from one central policy.
- `WalletService::post(command)`, `reserve(command)`, `commit(reservationId, orderId)`, `release(reservationId, reason)`, `reverse(command)`.
- `OrderRewardService::reconcile(orderId)` computes the desired cumulative state and posts only the difference from already recorded effects.
- `ReferralService::capture(code)`, `attribute(orderContext)`, `approve(conversionId)`.
- `LeaderboardService::prepare(month)`, `preview(month)`, `finalize(month)`, `publish(snapshotId)`.
- `RewardService::assign(...)`, `open(ownerId, assignmentId)`, `claim(...)`, `fulfill(...)`.
- `NotificationService::emit(eventKey, recipientId, payload)` writes an idempotent outbox record; adapters never run synchronously in checkout.

Controllers validate authentication, capability, nonce and input, then call services. Business rules never live independently in shortcodes, REST handlers or WooCommerce hooks. Documented filters can narrow eligibility and extend adapters; hooks cannot bypass money validation, ownership or balance locks.

## 3. Database design

All names below receive the actual `$wpdb->prefix . 'ghr_'`. IDs use unsigned BIGINT; user references always use WordPress user IDs. No email, telephone or referral code is a foreign key. Monetary amounts are signed BIGINT in paisa. Seeds are integer units, valued at 100 paisa per seed on redemption. Percentages use integer basis points. Timestamps are UTC, displayed in the site timezone. Monthly boundaries are calculated in the configured WordPress timezone.

Versioned migrations use a schema version option, a migration lock, resumable backfills and pre/post checks. `dbDelta` handles supported additive schema operations; explicit indexed migrations handle constraints it cannot safely maintain. Do not mark a migration complete until required checks pass. Avoid fragile cross-table foreign keys to WordPress core; enforce references within services and reconciliation checks.

| Table | Main fields and invariants | Key indexes |
|---|---|---|
| memberships | id, user_id, permanent public member_id, referral_code, status, registered_at, activated_at, phone, verified_phone, email_verified_at, public_consent, internal notes reference | UNIQUE user_id/member_id/referral_code/verified_phone; status; registered_at |
| identity_verifications | user_id, purpose, hashed token, normalized target, expires_at, attempts, consumed_at | token digest unique; user/purpose/expiry |
| membership_events | user_id, event, source_order_id, immutable reference, timestamp | unique event reference; user/date |
| wallet_accounts | id, user_id, wallet_type, posted_units, reserved_units, held_units, updated_at | UNIQUE user/wallet; invariants on writes |
| wallet_transactions | id, account_id, user_id, kind, signed units, status, source_type/ref, order_id, order_item_id, idempotency_key, actor, reversal_of, timestamp | UNIQUE idempotency_key; account/date; source; order; reversal_of |
| credit_lots | credit_transaction_id, account_id, original_units, remaining_units, expiry_at | unique credit transaction; account/expiry |
| credit_allocations | lot_id, debit_or_reservation_ref, units, allocation state | unique allocation key; lot; reservation |
| wallet_reservations | opaque id, account_id, checkout/session digest, order_id nullable, units, state, expires_at, idempotency_key | UNIQUE idempotency_key; account/state; state/expiry; order |
| reward_entitlements | user_id, benefit, scope, first_source_order_id, disposition | UNIQUE user/benefit/scope; source order |
| order_effects | order_id, effect_key, item allocation, policy version, eligible units, cumulative desired/posted units, revision | UNIQUE order/effect; order |
| referral_visits | opaque visit id, referrer_user_id, pseudonymous visitor digest, timestamp, flags | referrer/date; visitor/date; retention timestamp |
| referral_attributions | opaque attribution id, referrer_user_id, customer_user_id nullable, signed-token digest, visit_id, expiry, model, consent | token digest unique; customer/expiry; referrer |
| referral_conversions | order_id, buyer_user_id nullable, referrer_user_id, attribution_id, eligible amount, commission units, status, release_at, risk flags | UNIQUE order_id; referrer/status/date; release_at |
| monthly_scores | month, user_id, rules_version, eligible personal spend, orders, conversions, referral value, seeds, category scores | UNIQUE month/user/rules_version; month/category score |
| monthly_results | snapshot id, month, version, rules JSON, immutable ranked/award entries, digest, supersedes_id, created_by, created_at | UNIQUE month/version; month/date |
| result_publications | snapshot_id, action, actor, consent policy, reason, timestamp | snapshot/date; unique publication event key |
| reward_definitions | id, type, validated configuration, stock/quota, active state | type/state |
| reward_campaigns | id, period, category, published selection policy, definition ids, capacity, expiry | period/state |
| reward_assignments | opaque box id, owner_user_id, campaign_id, snapshot slot, preassigned reward, state, all lifecycle dates, delivery status, explanation | UNIQUE snapshot slot; owner/state; expiry |
| notifications | id, owner_user_id, unique event key, category, title/message, safe link, read_at, created_at | UNIQUE owner/event; owner/read/date |
| notification_deliveries | notification_id, channel, state, attempts, next_attempt, provider reference, redacted error | UNIQUE notification/channel; state/next attempt |
| notification_preferences | user_id, channel/event preferences, consent timestamps | UNIQUE user_id |
| audit_logs | immutable event id, actor/trigger, subject, operation, reason, redacted before/after, correlation id, timestamp | subject/date; actor/date; correlation |
| reconciliation_cases | source/target user ids, evidence references, proposed operations, reviewer, approval, execution status | status/date; source/target |

Balances are account projections updated in the same database transaction as ledger postings. Available = posted − reserved − held, floored to zero for display only. Negative posted balances can arise from reversing already-spent earnings; they represent recoverable reward debt and block further redemption. They must never be silently forgiven or hidden in reconciliation. Normal spending and admin debits cannot create a negative available balance.

Historical financial transactions and snapshots cannot be edited/deleted from management screens. Reversals create new linked rows. Transaction status changes are represented by linked lifecycle records/postings; no rewriting earned amounts. Database access uses prepared statements and indexed, paginated reads. Health checks compare projections with ledger sums without using those expensive sums for ordinary frontend reads.

## 4. Financial policy proposed for approval

### Earning basis

- Default final award trigger is `completed`, including COD and recorded offline orders. Payment success alone does not finalize rewards. Configurable earlier statuses display a risk warning and use the same reversible lifecycle.
- Eligible net product value = eligible line totals after ordinary coupons and plugin wallet discounts, excluding product tax, shipping, fees and ineligible items. Partial refunds reduce the matching item quantities/value. Central allocation prevents counting redeemed benefits as new purchases.
- Selected product/variation/category exclusions apply before the minimum eligible subtotal and rewards calculation. Parent product eligibility includes its variations unless excluded explicitly. Refunds use the original policy snapshot, not newly changed settings.
- Convert WooCommerce decimal strings to integer units with deterministic decimal parsing. No PHP float arithmetic in financial services. Referral commission rounds half-up to paisa once per order; Seeds round down once per order to whole seeds. Allocate discounts/refunds across lines using deterministic largest-remainder allocation.
- Membership activates once on the first positive eligible successful purchase. Existing registered members remain pending until eligible; suspended/closed members do not earn or redeem. Existing history and balances remain intact.
- Introductory award: 10,000 paisa once per member lifetime, only for explicitly selected inaugural product/variation eligibility. No product selected means bonus disabled with setup guidance. A ৳999 order total alone never qualifies. Refund of the qualifying package reverses its bonus; the lifetime entitlement remains consumed to prevent repeated farming. Exceptional reinstatement requires an audited administrator action.
- Seeds: 100 basis points of personal eligible spend. Referral: 200 basis points of referred eligible spend. One order can earn the purchaser Seeds and a different member referral commission, with independent ledger entries.
- Referral commission defaults to completed orders, zero additional hold days and no manual approval. Administrators may enable a timed hold/manual approval before availability. Pending amounts are shown separately and cannot be spent.
- Default: guest referral purchases do not qualify; repeat registered-customer purchases qualify; self-referrals are rejected. Guest support can be enabled only with order-backed identity checks. Fraud holds cover verified phone/account matches, matched billing contact signals and abnormal click rates without treating a shared IP as conclusive fraud.

### Redemption

| Wallet | Minimum available balance | Eligible order value before plugin credit | Maximum credit |
|---|---:|---:|---:|
| Trust Bonus | positive balance | ৳1,000 | eligible available balance |
| Referral | ৳500 | at least the requested credit | eligible available balance |
| Trust Seeds | 500 whole seeds | ৳700 | 500 seeds per order |

- Default one wallet per order; default minimum remaining payable product amount ৳0. Shipping and tax remain outside wallet coverage. Credit cannot exceed eligible products or make payable totals negative.
- A requested amount is bounded by server-calculated availability, thresholds, per-order cap and cart eligibility. The client supplies a wallet selection and desired amount, never an authoritative balance.
- Optional stacking is limited to explicitly supported, tested combinations with order Trust → Referral → Seeds; thresholds use the original eligible subtotal before plugin credits and each allocation reduces the remaining product credit capacity. Do not expose a switch for unsupported combinations.
- Trust Bonus cannot be redeemed in its earning order. No wallet permits cash withdrawal or member transfers. Identity reconciliation is an exceptional administrative procedure, not a transfer feature.
- Native WooCommerce discount/coupon calculation supplies product allocations and accurate tax reporting; no negative-fee workaround. The adapter must preserve the configured prohibition on covering tax by adjusting the product credit using WooCommerce tax calculations and verifying the resulting order totals. If a tax/coupon configuration cannot satisfy this invariant, reject redemption with an explanation; do not change gateway amounts. Test exclusive/inclusive tax, mixed rates, coupon order and refunds before declaring support.

### Concurrency, retries and refunds

1. An authenticated checkout command locks the relevant membership/account rows in a fixed order inside an InnoDB transaction. Revalidate status, lot expiry, available units and cart eligibility, then reserve units atomically. A nonce alone is not an idempotency key.
2. Reservations bind to an opaque checkout intent, authenticated owner and current cart fingerprint; each tab has its own intent. Default unbound lease 20 minutes, refreshed only after revalidation. Cart/coupon changes invalidate or reduce the reservation. Duplicate requests return the existing result.
3. On order creation bind the reservation and exact line allocations to the WooCommerce order using CRUD metadata. Classic checkout and Checkout Blocks call the same service. Reserve before allowing the discount to finalize; an allocation mismatch stops checkout.
4. Commit the debit at accepted order placement, including COD. Do not release a bound reservation solely because a short checkout lease expired. A recoverable order-effect saga bridges WooCommerce persistence and custom-table transactions; deterministic effect keys and queued reconciliation handle crashes between steps.
5. Failed/cancelled orders restore committed credit once, or release an uncommitted reservation. A later retry must reacquire/commit credit safely before restoring the order benefit; if funds are unavailable, require checkout recalculation. No order receives a free discount after a released reservation.
6. Refund reconciliation uses cumulative WooCommerce refund line data, recorded item allocations and unique effect revisions. Reverse earned rewards in proportion to refunded eligible value and restore the associated redeemed wallet portion separately; WooCommerce cash refund remains limited to money actually paid. Full refunds unwind all eligible effects. Shipping-only refunds do not reverse product rewards.
7. Amount-only refunds without item allocations and refunds handled outside WooCommerce enter an explicit reconciliation hold/review workflow. Administrators supply auditable item allocation before the financial effect is finalized; never guess the product basis silently.
8. If earned credit was already spent, post the reversal as reward debt, hold the account against redemption and recover against future earnings; preserve every original ledger event. A refunded redemption restores original credit lots/expiry provenance; already-expired credit is recorded as expired rather than silently renewed.
9. Status oscillations do not reissue the lifetime bonus or duplicate rewards. Requalification after a cancellation reconciles to the same entitlement with compensating postings, requiring the order's redeemable discount state to be valid first.
10. Expiry defaults off. When enabled, credit lots are consumed earliest expiry first; reservation, commit, release and reversal preserve lot provenance. Holds and expiries never mutate historical credits.

## 5. Membership, verification and privacy

- Membership enrollment never creates a second WordPress account. Registration stays within configured WooCommerce registration; guests must authenticate and verify email before joining. Existing WordPress user ID remains the relationship anchor when email changes. Changing email invalidates only email verification, not membership ownership/history.
- Pending email-verification membership records cannot earn or redeem until verified. Activation additionally requires a qualifying successful purchase. Member IDs are generated from a non-reused sequence (`GH-000001` minimum padding); referral codes use collision-checked cryptographic randomness.
- Phone normalization: Bangladesh country code and canonical E.164 form, validating supported mobile prefixes. Only verified phone has a unique constraint; unverified input does not claim ownership. Verification requires a configured SMS adapter or documented evidence-based admin verification. Never mark a phone verified merely because it was entered at checkout.
- Verification tokens are hashed, purpose-bound, expiring, attempt-limited and single-use; endpoints return non-enumerating responses. Default TTL 10 minutes, five attempts and limited resends. Email verification links have a separate bounded lifetime.
- Identity reconciliation requires dedicated capability, evidence, dry run, explicit confirmation and audit. Freeze affected financial accounts during execution; retain source records and public IDs as historical aliases, move ownership through recorded reassociation operations, and deduplicate lifetime entitlements. Do not merge/delete WordPress accounts or rewrite WooCommerce orders automatically. Detect referral loops and bonus duplication before execution.
- Public ranking uses Member ID by default. Display names and winner announcements require explicit consent. Referrer reports use opaque conversion references and permitted aggregates; never expose the buyer's identity/address or links that reveal private order information.
- Proposed retention: pseudonymous referral visits 90 days; consumed verification tokens 30 days; notification delivery diagnostics 90 days; read notifications 365 days. Financial/audit/snapshot records are preserved pending an administrator-approved legal retention policy. Document consent and cookie behavior; integrate with a configurable consent adapter and avoid persistent referral tracking before required consent.
- Privacy exporters verify ownership. Erasure anonymizes optional profile/visitor data and explains why statutory financial records remain. Secret values never appear in logs, exports or REST responses.

## 6. Routes, endpoints and shortcode coverage

Activation creates only missing mapped pages, using plugin page metadata plus slug/content checks. Never overwrite unrelated pages. Conflicts produce a wizard choice of mapping or alternate slug. My Account URLs always use WooCommerce helpers and the configured account page.

| Public route | Function |
|---|---|
| `/membership/` | benefits, earning/redemption rules, WooCommerce registration CTA, FAQs |
| `/refer/` | campaign landing with validated referral token/code |
| `/refer/{code}/` | same attribution pipeline and landing response |
| `/?ghref={code}` | configurable query parameter; same tracking service |
| `/leaderboard/` | month/year/category selector, scores, eligibility, rules, timestamp |
| `/leaderboard/winners/` | dated published winner snapshots |

Account defaults: `gh-membership`, `gh-trust-wallet`, `gh-referrals`, `gh-trust-seeds`, `gh-rewards`, `gh-notifications`. Labels, slug, order and visibility configurable, preserving every existing WooCommerce menu entry. Rewrite flushing occurs only on activation/deactivation and a validated route migration. Direct links, refresh and browser navigation are tested.

Shortcodes, all isolated, composable, ownership-aware and supported in normal content, Elementor, Bricks and block editor shortcode contexts:

| Group | Shortcodes |
|---|---|
| Membership | `ghr_membership_card`, `ghr_membership_dashboard`, `ghr_member_id` |
| Trust | `ghr_trust_wallet`, `ghr_trust_wallet_balance` |
| Referral | `ghr_referral_dashboard`, `ghr_referral_link`, `ghr_referral_stats`, `ghr_referral_wallet`, `ghr_referral_wallet_balance` |
| Seeds | `ghr_trust_seeds`, `ghr_trust_seeds_balance`, `ghr_trust_seeds_progress` |
| Competition | `ghr_leaderboard`, `ghr_monthly_winners`, `ghr_leaderboard_preview` |
| Rewards | `ghr_my_rewards`, `ghr_magic_box`, `ghr_rewards_summary` |
| Notifications | `ghr_notification_bell` |

Allowlisted attributes cover layouts/labels/currency/number formatting and bounded month/year/category/entry counts. Never accept owner IDs in customer-facing financial shortcodes. Logged-out users receive appropriate login/registration links, not personalized data.

### REST namespace: `ghr/v1`

| Route | Policy |
|---|---|
| `GET /me`, `/me/wallets`, `/me/transactions`, `/me/referrals`, `/me/rewards`, `/me/notifications` | authenticated owner; paginated and no-store |
| `POST /membership/enroll`, `/verification/request`, `/verification/confirm` | authenticated identity, nonce, rate limit; secure token validation |
| `POST /checkout/reservations`, `DELETE /checkout/reservations/{id}` | authenticated owner, nonce, checkout/cart binding; calculated server-side |
| `POST /rewards/{id}/open`, `/rewards/{id}/claim` | owner, nonce, locked state transition |
| `POST /notifications/{id}/read`, `/notifications/read-all`, `/me/preferences` | owner, nonce, validated preferences |
| `GET /leaderboard`, `/winners` | public published aggregates; bounded queries/cache |
| `/admin/members`, `/admin/adjustments`, `/admin/commissions`, `/admin/reconciliation`, `/admin/results`, `/admin/campaigns`, `/admin/reports`, `/admin/health` | route-specific capabilities, nonce and audit for mutations |

Store API extension data and supported cart update callbacks expose checkout state to Blocks; no reliance on classic checkout hooks or frontend DOM injection for Blocks. Use documented `registerCheckoutBlock`/extension APIs compatible with the agreed WooCommerce baseline. REST errors include stable codes without revealing SQL or private identity data.

## 7. Referral attribution rules

- First-party Secure (HTTPS), HttpOnly, SameSite=Lax opaque signed attribution cookie; 30-day default. Last valid click by default; first-click configurable. Never store raw user IDs or personal details in URLs/cookies.
- Every accepted URL uses one validator/capture service. Check active referrer, consent, expiry and rate limits. Deduplicate repeat visit events for reporting while retaining suspicious-activity aggregates. Visitor fingerprints are short-lived keyed digests; no raw IP retention by default.
- Preserve immutable attribution/order policy metadata through WC CRUD at checkout. Order-based conversion, not cookies, determines commission. Final self-referral checks run using the actual order customer and permitted contact signals.
- Reassignment defaults off. An authorized admin may enable it only before final reward posting; later corrections require audited reversals. Offline admin/POS orders use the same checks and finalization hooks, with a member finder by email, Member ID or verified phone. No automatic guest-to-member account creation.

## 8. Monthly competition and reward rules

- Timezone: use WordPress site timezone; wizard recommends `Asia/Dhaka` and asks before changing it. Calendar months use local boundaries converted to UTC for queries.
- Qualification: active member with at least ৳500 net eligible completed personal purchases during the selected month, adjusted for refunds.
- Default regular-customer score = eligible personal spend in paisa. Referrer score = qualifying completed referral conversions, then eligible referral value as the first tie-break. Seeds score = whole personal Seeds earned, net of reversals.
- Configurable bounded weights can combine published metrics; no executable user-entered formulas. Freeze a version of rules per month. Ties resolve by personal eligible spend descending, then earliest qualifying activity in that category, then public Member ID ascending. All tie-breakers appear in the rules.
- Category allocation order: regular customers, referrers, Seeds earners. For each category allocate top-ranked prize then Magic Box to the next-ranked eligible, unawarded member. Thus maximum one slot per member per month. Magic Box selection is deterministic, not random. Empty categories/insufficient eligible members leave slots unfilled with an explanation; never invent six winners.
- On the first day of the next month, queue prior-month preparation. Admin previews and finalizes a versioned immutable snapshot, then publishes separately. Changes after publication produce a new snapshot referencing the old one with an audit reason; old versions remain accessible for audit. Late refunds trigger a correction review rather than silent history changes.
- Awards require configured prize definitions and inventory/quota validation. No invented product or monetary prize values. Empty prize configuration blocks assignment/publication of affected awards with a setup warning.
- Magic Box prize is assigned server-side before display. Lifecycle: assigned/unopened → opened → claimed → fulfilled; expired/revoked are explicit exceptional states. Opening is idempotent and never consumes a second prize. Campaign/stock locks prevent oversubscription.
- Prize types: free product, fixed product credit, percentage discount, fixed coupon, special gift, manual reward. Product prizes use a zero-priced eligible WC order/coupon flow with explicit inventory handling; coupons are owner-bound, single-use and expire as configured. Gift/manual fulfillment requires auditable staff confirmation. Percentage/coupon caps and eligible products are part of the definition.
- Reward expiry defaults off. Claims of already opened/claimed/expired/revoked rewards return the existing valid state or a safe explanation. Animation reveals no hidden prize data before the authorized open response; support reduced motion and keyboard/screen-reader operation.

## 9. Administration, settings and frontend acceptance

Top-level **Ghoroa Hat Rewards** with Dashboard, Members, Membership Approvals, Trust Wallet, Referral Management, Referral Wallet, Trust Seeds, Monthly Leaderboard, Winners & Magic Boxes, Notifications, Reports, Settings, Activity & Audit Logs, System Health.

Dedicated capabilities: view reports, manage members, verify identity, adjust wallets, approve commissions, manage competitions, manage rewards, manage notifications, manage settings, reconcile identities. Administrators receive them at activation; shop managers receive only explicitly assigned permissions. Customers never receive administration capabilities.

Every required KPI, profile detail, transaction column, wallet summary, referral metric and reward state in sections 5–13 is an acceptance checklist item. Tables support pagination, permitted filters, date ranges and server-side searches. Adjustments require member, wallet, integer amount, reason and unique reference; actor/time are server-generated. Hold/release/reverse are distinct audited commands.

CSV reports cover members, monthly registrations, linked orders, referral sales/commissions, wallet transactions/redemptions, Seeds, results, fulfillment, deliveries and outstanding liabilities. Exports use capability checks, bounded/background generation, safe download tokens and formula-injection protection. Email/phone columns require elevated access.

Settings sections cover General, Trust Wallet, Referral, Seeds, Leaderboard, Notifications, Checkout and System. Include module toggles, pages/endpoints, branding, Bengali numbers/currency, eligibility/exclusions, rates, expiry, attribution, approval/hold, scoring/prizes, templates, checkout order/stacking/refunds, diagnostics/export. Validate data types, ranges, cross-setting consistency and supported combinations. Wizard requires products/prizes/adapters to be selected rather than inventing business configuration.

Frontend: Bengali-first translatable strings, #087A3D/#075B2A green, #D71920/#A80F16 accents, warm cream, scoped CSS, responsive cards/tables, accessible multiple-instance dropdowns, focus/escape handling, polite refresh announcements, empty/loading/error/success states. Conditional enqueue only on applicable account routes, mapped pages or pages rendering components. Private responses use no-store and never enter public caches. Header components refresh through authenticated APIs without jQuery.

## 10. Notifications and background work

- Support every event in requirement section 12, with centrally registered merge tags, per-event templates, account channel, email and optional modular SMS. Account events remain visible even if an optional delivery channel is disabled.
- Deliver email through WordPress/WooCommerce mail APIs. SMS disabled until adapter, destination, credentials and consent are configured; no API credentials shipped. Redact delivery diagnostics.
- Outbox uniqueness = recipient + business event/effect revision + channel. Action Scheduler claims delivery work. Retry transient failures at bounded backoff, default maximum five attempts; permanent failures are visible in health/admin. For providers supporting idempotency keys, reuse them. SMTP cannot guarantee exactly-once delivery across a crash after send; record this limit and use stable message identifiers without claiming impossible delivery guarantees.
- Schedule order financial reconciliation, expired attribution/visit cleanup, lot expiry, stale unbound reservation release, bound reservation/order recovery, pending commission release, reward reconciliation/expiry notices, periodic leaderboard aggregation, prior-month preparation, notification delivery/retries and failed-job recovery.
- Jobs are bounded, paginated, idempotent and resumable. Action Scheduler is the queue; monthly timing is computed in site timezone with a due-month watermark and catch-up on missed runs. Provide cron/queue health diagnostics, last-success timestamps and manual safe retry tools.

## 11. Security, lifecycle and operational safeguards

- Prepared SQL, strict enum/type validation, output escaping by context, nonces, REST permission callbacks, route-specific capability checks, ownership checks on every object and download, and rate limits on verification/referral/financial actions.
- No arbitrary settings-driven SQL, HTML, PHP or outbound endpoints. SMS adapters validate approved destinations and use WordPress safe HTTP APIs. Do not expose secrets through settings reads or REST.
- Activation: dependency/version checks, transactional engine check, migrations, defaults, capabilities, non-destructive pages, endpoints, queue registration and wizard. Optional enrollment of existing customers is opt-in and batched. Reactivation resumes safely without duplicate pages/jobs/awards.
- Deactivation unschedules only plugin-owned jobs, safely releases unbound reservations and preserves bound order reconciliation state, records and WooCommerce data. Document reconciliation on reactivation and the operational need to resolve active checkout intents before maintenance.
- Uninstall preserves financial/member data by default. Destructive removal is a separate elevated-capability, explicit confirmation/export workflow; never silently delete WC users or orders. Financial retention policy may prevent deletion.
- Audit mutations and denied sensitive operations with correlation IDs; omit raw cookies/tokens/credentials. Provide financial projection/ledger reconciliation and export/backup guidance before upgrades.

## 12. Phased development and acceptance gates

| Phase | Work | Required gate |
|---|---|---|
| 0 — design approval | this architecture, schema, route map, finance policy and plan | explicit user approval before plugin code |
| 1 — foundation/identity | bootstrap, compatibility, migrations, settings/wizard, capabilities, memberships, verification, pages/routes, audit/privacy skeleton | install/reactivate/upgrade tests; no duplicate users/pages; email changes preserve ownership; duplicate verified phone rejected |
| 2 — financial core | integer money, ledger/lots, locks, entitlements, central calculation, reward posting/reversal, admin adjustment/holds | database-backed concurrency, retry, rounding, reversal and projection invariants pass |
| 3 — commerce/referrals | Trust Bonus, Seeds, referral tracking/conversions/review, CRUD hooks, offline order association | repeated hooks/statuses, product eligibility, guests/self-referrals, manual/COD/payment and refund matrix pass |
| 4 — redemption | shared reservation saga, native discount/tax allocation, classic checkout and Blocks extensions, order metadata/recovery | parallel sessions, cart/coupon/tax changes, order retries, failures, cancellations, partial/full refunds pass in both checkout types |
| 5 — competition/rewards | monthly metrics/rules, deterministic winners, immutable snapshots/corrections, campaigns/boxes, native prize fulfillment | timezone boundaries, ties, unique slots, consent, concurrent claim/open, inventory and expiry tests pass |
| 6 — interfaces/operations | full frontend shortcode/account library, admin management/KPIs/reports, notification adapters/preferences, all jobs, privacy tools | owner/capability tests, all report types, retries, export safety, mobile/accessibility and asset isolation checks pass |
| 7 — release verification | current and minimum WP/WC, HPOS on/off, performance/security review, docs, ZIP packaging/install test | publish exact test results; no unverified production-readiness claims or missing modules hidden as completed |

Each phase includes its tests and documentation rather than postponing security/coverage to the end. Later phases integrate with the earlier stable service interfaces. A failing required gate must be fixed or reported explicitly; no placeholder interfaces presented as finished features.

## 13. Verification matrix and release evidence

- Unit: integer decimal conversion/rounding, thresholds, allocation, exclusions, stacking, score weights/ties, token/phone normalization, attribution models and policy validation.
- WordPress + WooCommerce integration with real MySQL/MariaDB: activation/migration/reactivation, new/existing customer/email change, enrollment/activation, verified-phone uniqueness, identity reconciliation, first/repeat package purchases, referral eligibility/guest/self/repeat attribution, holds/approvals and lot expiry.
- Financial integration: real transactions with independent database connections/processes for concurrent reservation/spend, adjustment/reversal races, idempotency, crash recovery, account projections, refunded spent rewards/debt, cumulative partial/full refunds and repeated hooks.
- Checkout end-to-end: classic and Blocks; three separate wallets; thresholds; multiple tabs; coupons; taxable/non-taxable/mixed products; tax-inclusive/exclusive pricing; shipping; negative-total prevention; COD/online/manual orders; failed payment/retry/cancellation; guest restrictions; supported POS association.
- Competition/rewards: site-timezone month boundaries, minimum spend, scoring, deterministic tie/slot policy, monthly job catch-up, review/publish, immutable corrections, private identities/consent, assigned prize secrecy, concurrent open/claim, ownership, stock/quota and fulfillment/expiry.
- Notifications: deduplicated events, preferences/consent, template escaping, queued delivery, retry exhaustion, provider errors, no checkout-blocking sends and documented delivery limits.
- Security: role/capability and cross-user REST tests, CSRF, XSS, SQL injection, CSV injection, cookie/token tampering, rate limits, cache isolation and secret redaction.
- UI: all shortcode instances individually and together; Elementor/Bricks/manual theme checks; mobile/tablet/desktop; keyboard/screen reader/reduced motion; direct links/refresh/back; no assets on unrelated pages.
- Performance: document data volume, environment, query plans, ledger write contention, paginated reports, aggregate caching and queue throughput. Set measured budgets after a baseline rather than inventing untested claims.
- Release record names each command/environment/version, executed/passed/failed/skipped counts, manual results, known limitations and artifact checksum. PHP lint/static checks do not substitute for real WooCommerce integration. No module is called complete without its relevant gate.

## 14. Approval decision

Approve this design to start implementation, or request changes to it. In particular, approval accepts the proposed minimum versions, BDT-only initial finance, integer Seeds, lifetime bonus entitlement after refunds, default referral policies, one-wallet checkout, debt recovery, refund review for unallocated refunds, deterministic Magic Box selection, consent/retention defaults and manual publication workflow. Product IDs, actual prizes and optional SMS credentials remain installation-time configuration; no secret values are needed in chat.

The original specification ends: **“Do not begin coding until the technical architecture, database design, route map, financial rules and phased development plan have been reviewed and approved.”** This review gate is the reason implementation has not yet begun.
