# Architecture and financial policies

## Identity and modules

The immutable relationship is `wp_users.ID -> WooCommerce customer -> ghr_memberships.user_id -> rewards/ledger`. Authentication stays in WordPress. Permanent public GH- IDs use a non-recycled sequence; referral codes are separate random values. Normalized verified phones are unique, nullable fields. Protected reconciliation aliases handle confirmed duplicate people without rewriting historical order ownership.

`Plugin` wires hooks. `Installation` handles schema/lifecycle. `Membership`, `Wallet`, `Calculator`, `TrustBonus`, `TrustSeeds`, `Referral`, `ReferralCommission`, `OrderEffects` and `Orders` implement domain services. `Checkout` adapts native cart/order coupons and reservation state. `Leaderboard` and `Rewards` implement competitions. `Notifications`, `SMS`, `Jobs`, `Privacy`, `Reconciliation`, `Removal`, `Settings`, `Rest`, `Admin`, `Frontend`, `DB` and `Money` provide supporting interfaces. Business rules are shared by hooks and controllers.

## Default policy

- Final earnings require completed orders; COD payment/processing alone earns nothing final.
- Enrollment reuses existing WordPress customers. Email must be verified before activation/redemption. Suspended/closed members cannot redeem.
- Bonus: first completed eligible selected inaugural product grants ৳100 once for the person's lifetime; later Trust spending requires ৳1,000 eligible products. A refund can reverse the grant, but repeated package purchases cannot create a second entitlement.
- Referral: 2% eligible net products, 30-day last valid consented click; repeat purchases enabled, guests/self/reassignment disabled, no approval/hold delay by default. Identity matching produces review holds. Referrer earnings never generate personal Seeds.
- Seeds: floor of 1% eligible own net product value in whole Seeds; 1 Seed = ৳1; available minimum 500, order minimum ৳700, maximum 500/order.
- One wallet per order; no cash withdrawal or customer transfers. Unsupported combinations are rejected. Shipping and tax are outside the eligible credit base. Minimum payable and exclusions are configured centrally.
- Historical orders do not receive retroactive financial grants by default; activation can recognize a past qualifying completed purchase. Explicit historical rewards changes require deliberate administration.

## Arithmetic and WooCommerce totals

Money is integer paisa; Seeds are whole units. Percentage rates use integer basis points. Money::decimal serializes values for WooCommerce CRUD; there is no floating-point ledger arithmetic. Referral monetary rounding is half-up; Seeds round down. Partial allocations use exact largest-remainder distribution with overflow-safe integer arithmetic.

Eligible product base is native order line net totals after coupons and previous reward credits, excluding shipping/tax, with eligibility snapshotted per line. Coupons retain normal WooCommerce tax calculation: this can reduce taxable product value and consequently tax. The plugin does not create tax credits or manipulate gateway amounts. Inclusive-price carts use a native-total solver so the net product reduction exactly matches the reserved value.

## Ledger and order processing

Accounts cache posted/reserved/held/earned/redeemed/reversed totals. Immutable signed ledger entries and unique idempotency keys are authoritative. All mutations use InnoDB transactions and member/account row locks. Credit lots preserve expiry and usage provenance. Available = max(0, posted - reserved - held). Read summaries do not sum the full ledger.

Checkout reserves available credit, binds it to an order, commits exactly once on accepted order placement, and releases/restores through shared status/refund handlers. Unbound stale reservations expire; bound unpaid orders follow their order lifecycle. Failed/cancelled old discounted orders cannot silently reuse a released reservation. Late settlement is an explicit audited operation, optionally permitting recorded reward debt.

Order reconciliation uses a per-order advisory lock, immutable policy/item snapshots and cumulative desired-vs-posted deltas. Repeated hooks and status changes create only necessary delta entries. Partial refunds reduce eligible value and restore redeemed credit proportionally under policy; unallocated manual refunds require reviewed line allocations. A refunded spent grant creates debt only for actual unrecovered benefit; expired grants do not generate artificial debt. Credit expiry, refund restoration and debt-repayment provenance are tracked separately.

Custom-table ledger changes are transactional. WooCommerce order writes, Action Scheduler and external transports form a recoverable saga rather than a distributed ACID transaction. Idempotent keys, stored order metadata, reconciliation queues and staff review cover partial failures.

## Monthly rules

Site-calendar boundaries are converted to UTC for storage/query. Defaults rank Regular by personal paisa, Referrals by approved conversion count (referral value tie-break), and Seeds by personal Seeds; additional nonnegative configured weights support explicit recipes. Eligibility requires ৳500 personal spend in the month. Ties use personal spend, earliest category activity, then permanent member ID (with referral value preceding those for referrals). Alternative approved tie policies are configurable.

Monthly preparation aggregates indexed custom tables in SQL with constant PHP row memory; only a bounded union of top category candidates is returned for six slots. Each category selects top available prize winner and next available Magic Box member, in Regular/Referrals/Seeds order. A member gets at most one slot. No random selection is used.

Past results are immutable versioned snapshots containing rules, rankings and selections. Publication records point to a reviewed version. Corrected versions are audited and cannot silently replace claimed prizes. Winner display identities require consent; public rankings contain only permanent public IDs and scores. Owner details remain private.

## Security and retention

Owner REST routes use logged-in cookies and the WordPress REST nonce; administrative actions require dedicated capabilities. User IDs in customer payloads cannot redirect ownership. Queries prepare values; identifier fragments are fixed/allowlisted. No public financial ledger API exists. Referral URLs/cookies exclude customer contact data. Verification tokens are hashed, purpose-bound, expiring, single-use and rate limited. Short-lived queued verification payloads and SMS credentials use authenticated encryption; public HTML is escaped. Public SQL identifiers and score recipes never come from arbitrary request text.

Action Scheduler executes bounded maintenance/recovery and notification retries. Financial posting idempotency is independent of message delivery. SMS sends an idempotency header; SMTP cannot promise exactly-once delivery after ambiguous transport failures. Privacy erasure retains necessary financial/audit evidence and anonymizes optional fields. See administrator guide for retention and removal behavior.
