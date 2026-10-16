# Administrator guide

## Access and member operations

The top-level Ghoroa Hat Rewards menu provides Dashboard, Members, Approvals, Trust Wallet, Referral Management, Referral Wallet, Trust Seeds, Leaderboard, Rewards, Notifications, Reports, Settings, Audit and System Health. Administrators receive the plugin's custom capabilities on activation. Grant narrower capabilities deliberately; ordinary customers receive none.

Find members by public ID, account email, phone or user ID. Open a member profile for linked native WooCommerce orders, balances, referral records, rewards and internal notes. Enrollment reuses the WordPress customer. Pending members activate after a qualifying completed purchase and verified email. Suspension retains history and funds while preventing new redemption. Email changes require reverification and preserve ownership.

Phone verification normalizes Bangladesh numbers and enforces uniqueness. Staff verification requires evidence recorded in the reason field. Never use a phone or email as a replacement account key.

## Financial administration

Wallet amounts in administrative commands are **integer paisa for trust/referral**, and **whole Seeds for seeds**. Thus `10000` means ৳100 in Trust Wallet; `100` means 100 Seeds. Frontend checkout inputs show normal taka/Seed units.

Credit/debit adjustments require a member, wallet, unique reference and reason. Repeated references are idempotent. Hold uses a signed delta: positive reserves an administrative hold, negative releases it. Ledger records cannot be edited or deleted. Correct order earnings through order/refund reconciliation; use separate adjustments for discretionary goodwill.

Refunds use WooCommerce native line refunds and cumulative reconciliation. Unallocated manual refund amounts require reviewed product-item allocations; enter item IDs and paisa in the review command. If a member already spent reversed earnings, the wallet may carry reward debt and future credits repay it. Members cannot spend a negative balance.

Failed/cancelled order credit is released. A late paid callback after release is flagged for review; it does not silently double-spend credit. Resolve with Late Settlement after checking the payment/order; debt requires the additional exact text `ALLOW REWARD DEBT`. This command does not alter gateway transaction amounts.

## Offline orders

Create a native WooCommerce admin order or supported POS order. Find an existing member by email, public member ID or verified phone in the plugin member tools and select that WordPress customer on the order. Add authorized referral attribution before finalization. Complete through WooCommerce; the same reward service handles online, manual and COD orders. Unrecorded sales never earn rewards.

## Referrals

Review attributed orders, pending/held approvals and matching buyer/referrer identity flags. Cookie visits alone never earn commission. Approving a commission requires a reason; reconciliation rechecks the actual order. Reassignment is disabled by default. Self-referral exceptions require explicit setting plus manual approval. No cash payouts or ordinary customer wallet transfers are provided.

## Competitions and rewards

Rules freeze per month. Global setting changes apply to the next unfrozen month; use Month Rules with a reason for an explicit correction. Scores use published weights. Default eligibility is ৳500 personal monthly spend. Default category order is Regular, Referrals, Seeds. Each category takes its highest available member and the next ranked eligible member for a Magic Box; each member receives at most one slot per month. Fewer eligible members produce fewer awards.

Prepare scores, review rankings, finalize a versioned snapshot for a past month, then publish with a reason. Publication requires matching configured campaigns and adequate quota. Snapshots include rules, rankings and awards. A corrected snapshot is a new version with an audit reason. Unchanged awards are reused; unclaimed changed awards can be revoked with quota restoration. Claimed/fulfilled conflicts require explicit staff review and are blocked from silent replacement.

Create reward definitions (free product, product credit, percent, coupon, gift, manual), then campaigns with month/category/capacity/expiry. The assigned prize is chosen on the server before a Magic Box opens. Top prizes start opened; boxes start sealed. Native coupon prizes have an immutable user-ID owner and one-use limits. WooCommerce handles product stock at redemption. Mark physical/manual gifts fulfilled with a recorded delivery reason.

## Notifications, reports and privacy

Templates support `{member_name}`, `{member_id}`, `{order_number}`, `{reward_amount}`, `{wallet_balance}`, `{referral_code}`, `{reward_name}`, `{site_name}`. Use Notifications to inspect failures/retry after correction. An announcement to user 0 queues a bounded broadcast. Email and SMS follow customer preferences; account notices retain a history. SMTP/provider acceptance cannot guarantee exactly-once inbox delivery after a transport crash.

Reports provides 12 allowlisted CSV report types, member/date filtering where applicable and bounded cursor export. Dates follow site timezone. CSV cells starting with spreadsheet formula characters are escaped. Exports contain personal/accounting information and require protected storage.

WordPress Tools > Export/Erase Personal Data integrates with this plugin. Optional contact/display/notification data can be anonymized; financial, audit, fraud and reconciliation records remain for operational accounting. Raw visit/attribution records are pruned in bounded batches after 90 days, verification records after 30 days beyond expiry, and read notifications after a year. Document applicable retention obligations in the site's privacy policy.

## Duplicate identity reconciliation

This is a financial consolidation procedure, not a WordPress account merge. Review evidence that both accounts belong to the same person. Resolve unpaid source orders, active bound reservations and duplicate monthly award conflicts first. Generate a preview, inspect balances/history/bonus conflicts, then enter the exact provided confirmation.

The source membership closes; the target becomes canonical. Immutable reconciliation postings migrate liabilities and lot expiry/debt provenance. Old WordPress customers, WooCommerce orders and ledger history retain their original IDs. The canonical member can see historical orders and financial history. Duplicate lifetime bonuses are corrected. Keep the reconciliation case and audit evidence.
