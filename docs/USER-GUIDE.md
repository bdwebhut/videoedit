# Member guide and shortcodes

Use the store's usual WooCommerce email login. Join from My Account > Membership; verify your email and complete a qualifying purchase to activate. Your permanent GH- member ID remains yours when your email changes. Verify the new email before redeeming rewards. Phone verification is additional contact identity and does not change your login.

The Trust Wallet holds the one-time selected-package bonus; Referral Wallet holds qualified referral commission; Trust Seeds comes from your own eligible purchases. Each account page shows available, pending, lifetime earned/redeemed/reversed values, ledger history and conditions. Filter wallet history by date, kind and direction. Credits are for products; they are not cash and cannot be transferred.

Copy/share your referral URL from Referrals. Visitors need the configured consent before tracking. Pending orders do not create spendable commission. Buyer identities stay private. Seeds progress shows your current spendable balance against the threshold.

At checkout, select one eligible wallet, enter the amount and apply. The server reserves your balance. Changing the cart may invalidate eligibility; remove/reapply if prompted. Cancellation, failure and refunds follow the displayed policy. Rewards and notifications remain accessible through their account tabs.

Open an assigned Magic Box in My Rewards to reveal its predefined prize, then claim before expiry. Active verified membership is required to claim and redeem. Coupon prizes work only for their owner; physical gifts await fulfillment. Notification preferences include channel and consent settings.

## Shortcodes

Insert in normal WordPress content, block-editor Shortcode blocks, or the shortcode widgets provided by a page builder. No jQuery required. Owner components show login/enrollment prompts when appropriate. Financial components never show another customer's balance.

| Shortcode | Purpose |
|---|---|
| `[ghr_membership_information]` | Public benefits and enrollment CTA |
| `[ghr_referral_landing]` | Public referral entry |
| `[ghr_membership_card]` | Compact owner card |
| `[ghr_membership_dashboard]` | Full membership dashboard |
| `[ghr_member_id]` | Owner public ID |
| `[ghr_trust_wallet]` | Compact accessible Trust dropdown |
| `[ghr_trust_wallet_balance]` | Trust balance text |
| `[ghr_referral_dashboard]` | Owner referral details |
| `[ghr_referral_link]` | Copy/share link |
| `[ghr_referral_stats]` | Owner statistics |
| `[ghr_referral_wallet]` | Referral dropdown |
| `[ghr_referral_wallet_balance]` | Referral balance text |
| `[ghr_trust_seeds]` | Seeds dropdown |
| `[ghr_trust_seeds_balance]` | Seeds balance text |
| `[ghr_trust_seeds_progress]` | Threshold progress |
| `[ghr_leaderboard]` | Public rankings |
| `[ghr_monthly_winners]` | Published archive |
| `[ghr_leaderboard_preview]` | Compact rankings |
| `[ghr_my_rewards]` | Owner rewards |
| `[ghr_magic_box]` | Owner assigned boxes |
| `[ghr_rewards_summary]` | Owner reward totals |
| `[ghr_notification_bell]` | Notification dropdown and unread count |

Leaderboard example: `[ghr_leaderboard month="2026-09" category="regular" number="10" layout="table"]`. Categories are `regular`, `referrals`, `seeds`. Supported layouts and limits are sanitized. Balance shortcodes accept approved `label`, `style` and `currency` attributes; currency accepts symbol/code/none, style accepts text/badge. Settings controls Bengali digits and branding. Refer to `Frontend::shortcode()` for the complete attribute allowlists; arbitrary HTML attributes are ignored.

Header dropdowns use native accessible details controls, keyboard focus, Escape closing and refresh buttons. Multiple components can coexist. Existing WooCommerce account menu items are preserved. Pages and account URLs follow the store's configured permalinks.
