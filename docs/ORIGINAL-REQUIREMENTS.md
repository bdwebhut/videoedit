# GHOROA HAT — SMART MEMBERSHIP & REWARDS

## MASTER PLUGIN DEVELOPMENT REQUIREMENTS

**Project:** Ghoroa Hat Smart Membership & Rewards  
**Plugin Slug:** `ghoroa-hat-rewards`  
**Plugin Prefix:** `ghr_`  
**Text Domain:** `ghoroa-hat-rewards`  
**Brand:** Ghoroa Hat (ঘরোয়া হাট)  
**Parent Company:** BD WEBHUT PRIVATE LIMITED  
**Platform:** WordPress + WooCommerce  
**Plugin Type:** Standalone, modular, production-ready WordPress plugin  
**Frontend:** Responsive, Bengali-first  
**Admin:** Native WordPress dashboard with modern custom management interfaces  

## 1. Project Objective

Develop a complete custom WooCommerce-integrated membership, referral, wallet, loyalty, leaderboard, reward, and notification management plugin.

The plugin should convert ordinary WooCommerce customers into long-term Ghoroa Hat members.

Customers should be able to:

- Receive and manage permanent membership IDs.
- Earn a ৳100 introductory Trust Bonus after purchasing the eligible ৳999 package.
- Generate unique referral links.
- Track referral-link visits and qualified purchases.
- Earn a 2% referral commission from qualifying referred purchases.
- Earn 1% Trust Seeds from their own qualifying purchases.
- Redeem eligible wallet balances toward products.
- View monthly leaderboards and previous winners.
- Receive and open Magic Boxes assigned to their accounts.
- Track all rewards, transactions and notifications.
- Access all benefits using their existing WooCommerce accounts.

Administrators should be able to configure, verify, adjust, audit and report on every module without editing PHP files.

## 2. Critical User Identity Architecture

WooCommerce currently uses customer email addresses as the primary account source.

**This must remain unchanged.**

The permanent technical relationship must be:

WordPress User ID → WooCommerce Customer Account → Ghoroa Hat Membership Record → Wallets, Referrals, Rewards and Transactions.

Rules:

1. Use `wp_users.ID` as the immutable internal customer identifier.
2. Keep the existing WordPress/WooCommerce email authentication system.
3. Email address may change through normal WooCommerce account management without losing any membership data.
4. Never use a customer's email address, phone number or referral code as a database foreign key.
5. Generate a unique, permanent, non-sensitive Member ID, for example `GH-000001`.
6. Generate a separate unique Referral Code, for example `GHRA7K92`.
7. Mobile phone is an additional verified identity/contact field, not a replacement for email.
8. Enforce one verified phone number per individual member where practical, using normalized Bangladesh phone format.
9. Do not create duplicate WordPress accounts when registering an existing WooCommerce customer.
10. Guest registration must follow the configured WooCommerce registration process and collect a verified email before linking the membership.
11. Existing customers must be enrolled or migrated without creating new user accounts.
12. A user may be registered but have a pending membership until the first qualifying successful purchase.
13. Maintain distinct states: `pending`, `active`, `suspended`, `closed`.
14. Suspended members must retain transaction history and existing balances subject to clearly defined redemption restrictions.
15. Provide an admin-controlled customer identity reconciliation procedure for legitimate duplicate-account cases, with audit logs.

## 3. WooCommerce Integration

Integrate with WooCommerce natively.

Requirements:

- Support WooCommerce HPOS.
- Use `WC_Order`, `wc_get_order()`, `wc_get_orders()`, CRUD APIs and appropriate WooCommerce order hooks.
- Do not query `wp_posts` or `wp_postmeta` directly to handle orders.
- Support both classic checkout and WooCommerce Checkout Blocks, using supported integration APIs.
- Work with COD, online payments and manual orders.
- Support refunds, cancellations, partial refunds and failed payments.
- Handle the distinction between payment success and fulfillment success.
- By default, rewards become final only when an order is `completed`.
- Allow admin configuration of qualifying order statuses with an explicit warning about early award risks.
- Each order may generate its eligible Trust Seeds and Referral Bonus only once.
- Repeated status changes or webhook retries must not duplicate rewards.
- Track source Order ID and Order Item IDs where required.
- Support simple and variable products.
- Respect product exclusions and minimum eligible product subtotal.
- Define the treatment of coupons, tax, delivery charges and previously redeemed credits in one central reward-calculation service.
- Integrate existing WooCommerce My Account, checkout, order details and thank-you pages.

Do not replace WooCommerce payment processing, stock management or standard customer management.

## 4. Automated Installation and Pages

On activation, create only missing plugin-managed pages and register all required My Account endpoints.

Do not duplicate existing pages.

### Public Pages

**Membership Information**
`/membership/`

Display membership benefits, earning rules, registration CTA and FAQs.

**Referral Landing Page**
`/refer/`

Public landing page for referral campaigns, with tracking based on a valid referral code or token.

**Monthly Leaderboard**
`/leaderboard/`

Public leaderboard with monthly rankings and winners.

**Past Winners**
`/leaderboard/winners/`

Archive of finalized monthly results.

Use WordPress pages or rewrite routes as appropriate, resolving rewrite conflicts correctly.

### WooCommerce My Account Endpoints

Use the configured WooCommerce My Account base URL dynamically.

Suggested endpoint slugs:

- `/my-account/gh-membership/`
- `/my-account/gh-trust-wallet/`
- `/my-account/gh-referrals/`
- `/my-account/gh-trust-seeds/`
- `/my-account/gh-rewards/`
- `/my-account/gh-notifications/`

All endpoint slugs, display labels and menu visibility/order must be configurable from plugin settings.

Preserve all existing WooCommerce account menu items.

Pages and endpoints must support refresh, browser back navigation and normal direct links without 404 errors.

Flush rewrite rules only on activation, deactivation or necessary permalink migration, not on every request.

## 5. Membership Dashboard

Provide a dedicated membership endpoint displaying:

- Member Name
- Permanent Member ID
- Verified Email
- Mobile Number
- Membership Status
- Registration Date
- Activation Date
- Referral Code
- Total Successful Orders
- Lifetime Eligible Purchase Amount
- Available Trust Bonus
- Available Referral Wallet Balance
- Available Trust Seeds
- Current Monthly Performance
- Unopened Magic Boxes
- Recent Rewards and Notifications

Use compact summary cards and easy navigation to the detailed modules.

Membership state logic:

Pending membership → First qualifying successful WooCommerce purchase → Active membership.

The activation action must be idempotent.

Existing WooCommerce customers should be eligible to join and receive their own Member ID while retaining all current account information.

Provide optional shortcodes:

`[ghr_membership_card]`

`[ghr_membership_dashboard]`

`[ghr_member_id]`

These shortcodes must show only appropriate information to the logged-in owner.

## 6. Trust Wallet — Introductory Bonus

Create a dedicated Trust Wallet module.

### Default Business Rule

When a member purchases the qualifying ৳999 inaugural package for the first time and the order is successfully completed, issue a ৳100 Trust Bonus.

The bonus:

- Is issued once per qualified member.
- Cannot be claimed through repeated package purchases.
- Is unavailable before successful order completion.
- Can be redeemed on a later eligible order.
- Requires a minimum ৳1,000 eligible product value.
- Cannot be withdrawn as cash.
- Cannot be transferred between members.

The qualifying package must be selected in admin settings by WooCommerce Product ID or configured eligibility rules.

Do not assume that any arbitrary order worth ৳999 qualifies.

### Trust Wallet UI

Create a separate My Account tab:

`/my-account/gh-trust-wallet/`

Display:

- Available Balance
- Pending Balance
- Lifetime Earned
- Lifetime Redeemed
- Expired or Reversed Amount
- Detailed Transaction History
- Source Order
- Transaction Date and Time
- Transaction Status
- Expiry Date, when enabled
- Redemption Conditions

Provide pagination, filters and clear debit/credit indicators.

### Header Dropdown and Shortcode

Implement:

`[ghr_trust_wallet]`

Display a compact wallet button with an icon and current balance.

When clicked, open an accessible dropdown containing:

- Wallet Name
- Current Available Balance
- Pending Balance, if applicable
- Recent Transactions
- View Full Wallet button
- Redemption Rules

Requirements:

- AJAX/REST-powered balance refresh.
- Responsive on mobile.
- Keyboard accessible.
- Works inside Elementor, Bricks and normal WordPress content.
- Does not require jQuery.
- Supports multiple shortcode instances on one page.

Additional shortcode:

`[ghr_trust_wallet_balance]`

Allow admin-approved attributes for display style, currency formatting and labels.

### Redemption

Integrate available Trust Bonus into WooCommerce checkout as an order credit.

Validate eligibility server-side before applying.

Persist redemption references on the WooCommerce order.

Use a reliable reserve, commit and release process so a customer cannot spend the same balance in two simultaneous checkout sessions.

Refunds and order cancellation must follow configured reversal rules.

## 7. Referral System

Create a complete referral tracking and earnings module.

### Unique Referral Identity

Each active member receives:

- A unique Referral Code.
- A permanent Referral URL.
- A referral dashboard.
- Referral analytics.
- Referral earnings history.

Example:

`https://ghoroahat.com/refer/GHRA7K92/`

Also support a configurable query-string format:

`https://ghoroahat.com/?ghref=GHRA7K92`

All accepted referral URLs must resolve to the same tracking process.

Do not expose the member's email address, mobile number or WordPress User ID in the public referral URL.

### Referral Visit Tracking

When a visitor opens a valid referral link:

1. Validate the referral code.
2. Record an attributable landing visit or click event.
3. Set a secure, first-party referral attribution cookie or equivalent session marker.
4. Associate the referral source with checkout when eligible.
5. Prevent self-referrals.
6. Preserve the attribution data in WooCommerce order metadata.
7. Finalize commission only after order completion.

Provide default configurable attribution window of 30 days.

Default attribution model: Last valid referral click.

Admins must be able to configure:

- Attribution window
- First-click or last-click attribution
- Whether referral rewards apply to only the first purchase or multiple purchases
- Whether guest purchases qualify
- Whether referrals can be reassigned
- Whether customers can use their own referral codes (default: no)
- Excluded products or categories
- Earning percentages
- Commission approval requirements

Do not use browser cookies as proof of successful sales.

An actual WooCommerce order must be the source of any confirmed purchase.

### Referral Earnings

Default commission:

**2% of eligible net product value.**

Examples:

৳1,000 qualified purchase → ৳20 referral earnings.

৳5,000 qualified purchase → ৳100 referral earnings.

Commission becomes available after the qualifying order has successfully completed, subject to any configured hold/review period.

Cancelled, refunded or fraudulent orders must not produce final payable rewards.

### Referral Wallet

Keep the referral wallet completely separate from the Trust Wallet and Trust Seeds Wallet.

Minimum redemption threshold: ৳500.

When the available eligible balance reaches ৳500, the member may use it to obtain products of equivalent value from Ghoroa Hat.

Cash withdrawal and transfers between members are not permitted.

Provide checkout integration for redemption.

### Referral Dashboard

Endpoint:

`/my-account/gh-referrals/`

Display:

- Referral URL
- Referral Code
- Copy Link button
- Share buttons
- Total Link Clicks
- Unique Visitors
- Tracked Orders
- Completed Referral Orders
- Pending Referral Orders
- Qualified Referral Purchase Value
- Lifetime Referral Earnings
- Available Referral Wallet Balance
- Redeemed Referral Balance
- Monthly Referral Performance
- Detailed Referral Transaction History

Referral activity table:

- Date
- Referral Reference
- Status
- Order Reference
- Eligible Purchase Amount
- Earned Commission
- Payout / Redemption Status

Privacy rule:

Referrers must never see a referred customer's full name, email, phone, shipping address or other sensitive order information.

Use pseudonymous identifiers and aggregated values.

### Referral Shortcodes

`[ghr_referral_dashboard]`

`[ghr_referral_link]`

`[ghr_referral_stats]`

`[ghr_referral_wallet]`

`[ghr_referral_wallet_balance]`

Provide a compact header dropdown for the Referral Wallet, with a link to full referral details.

## 8. Trust Seeds Wallet

Create a separate loyalty reward module called:

**বিশ্বাস বীজ (Trust Seeds).**

### Earning Rule

For every successfully completed eligible personal purchase, award 1% of the eligible net product amount as Trust Seeds.

Examples:

৳1,000 purchase → 10 Trust Seeds.

৳5,000 purchase → 50 Trust Seeds.

Trust Seeds must not be automatically awarded for someone else's referral purchases.

### Redemption Rule

- Minimum available balance: 500 Trust Seeds.
- Minimum eligible new order value: ৳700.
- Maximum Trust Seeds usable on one order: 500.
- Redemption value: 1 Trust Seed = ৳1.
- Cannot be withdrawn in cash.
- Cannot be transferred to another member.

Support configurable earning percentage, minimum redemption, maximum redemption per order and required order subtotal.

### Trust Seeds My Account Endpoint

`/my-account/gh-trust-seeds/`

Display:

- Available Trust Seeds
- Pending Trust Seeds
- Lifetime Seeds Earned
- Lifetime Seeds Redeemed
- Seeds Reversed
- Purchase History
- Transaction History
- Redemption History
- Progress Toward Minimum Redeemable Balance
- Current Monthly Seeds Earned

Show an easy-to-understand progress bar, such as:

350 / 500 Seeds earned.

Include clear earning and redemption instructions.

### Shortcodes

`[ghr_trust_seeds]`

`[ghr_trust_seeds_balance]`

`[ghr_trust_seeds_progress]`

Provide compact balance dropdown support in the website header and mobile layout.

## 9. Unified WooCommerce Checkout Redemption

Customers may have three independent benefit balances:

1. Trust Wallet
2. Referral Wallet
3. Trust Seeds Wallet

The balances must not be combined into one database balance.

At checkout, display a dedicated "ঘরোয়া হাট সদস্য সুবিধা" section.

Each eligible benefit must have:

- Balance display
- Eligibility indicator
- Redemption amount
- Apply/Remove control
- Clear explanatory message

All final calculations and validations must execute on the server.

Never trust an AJAX-submitted balance or discount amount.

Prevent negative payable order totals.

Do not apply rewards toward shipping or tax unless an explicit configured rule allows it.

Create an explicit stacking policy.

**Safe initial default:** Only one benefit wallet can be redeemed per order, with the admin able to permit supported combinations after calculation rules have been tested.

The final checkout integration must safely handle:

- Multiple tabs/sessions
- Cart updates
- Coupon changes
- Checkout refresh
- Payment failure
- COD
- Cancelled orders
- Partial refunds
- Order retries
- Guest checkout restrictions
- Checkout Blocks and classic checkout

Do not directly manipulate payment gateway transaction amounts or the WooCommerce database outside supported APIs.

## 10. Monthly Leaderboard

Create a standalone public leaderboard at:

`/leaderboard/`

And previous winner archive at:

`/leaderboard/winners/`

### Eligibility

A member must have at least ৳500 of eligible successful personal purchases in the selected calendar month to qualify.

Timezone must follow the site's configured WordPress timezone, defaulting to Asia/Dhaka when explicitly configured.

### Three Competition Categories

**Category 1: Regular Customers**

Rank members using configurable monthly purchase activity scoring.

**Category 2: Successful Referrers**

Rank members using qualifying referral conversions and configured performance scoring.

**Category 3: Trust Seeds Earners**

Rank members by Trust Seeds earned through eligible personal purchases in the month.

Scoring formulas must be explicit, published and configurable.

### Monthly Prize Structure

Each category produces:

- One confirmed top-ranked prize winner.
- One additional eligible Magic Box recipient based on the published qualification and scoring policy.

Total: Six award slots across three categories per month.

Define tie-breakers and whether a member may win multiple categories.

Default recommendation: Maximum one award slot per member per month, with deterministic selection rules.

Do not silently choose Magic Box recipients without an auditable published selection method.

### Public Leaderboard

Display:

- Current month and year
- Category selector
- Top-ranked members
- Rank
- Public Member ID or masked display name
- Relevant score
- Qualification status
- Award labels
- Leaderboard last-updated timestamp
- Monthly rules

No private customer information should appear publicly.

### Monthly Results

Finalize the prior month's results on the first day of the next month, with an admin review/publish process.

For example:

September 2026 results → October 1, 2026.

Previous months must remain accessible in a dated archive.

Each finalized result should be stored as an immutable, versioned snapshot. Any corrections must be explicitly logged.

### Winners Archive

Display:

- Month and Year
- Competition Category
- Winner Display Name
- Member Public ID
- Award Type
- Award Status
- Announcement Date
- Delivery/Claim Status when publicly appropriate

Winners must be able to view their awards privately in My Account.

Public winner announcements must follow the site's privacy policy and appropriate consent settings.

### Shortcodes

`[ghr_leaderboard]`

`[ghr_monthly_winners]`

`[ghr_leaderboard_preview]`

Allow attributes for month, year, category, number of entries and layout.

## 11. Rewards and Magic Box

Create a dedicated My Account endpoint:

`/my-account/gh-rewards/`

### Rewards Dashboard

Display:

- My Available Rewards
- Unopened Magic Boxes
- Claimed Rewards
- Pending Delivery
- Completed Rewards
- Expired Rewards
- Reward History
- Prize Eligibility Rules

### Magic Box Functionality

Administrators can create Magic Box campaigns.

Each box may contain an eligible predefined prize.

A qualified member should see a sealed Magic Box card in their account.

Opening the box should reveal the already assigned prize through an accessible animation.

Do not determine prize outcomes through insecure frontend JavaScript.

Do not permit users to open or claim someone else's box.

Each Magic Box must have:

- Unique Box ID
- Owner Member ID
- Campaign Month
- Category
- Assigned Reward
- Assigned Date
- Opened Date
- Claimed Date
- Expiry Date when applicable
- Delivery Status
- Audit Log

Magic Box lifecycle:

Assigned → Unopened → Opened → Claimed → Fulfilled.

Support expired and revoked states with appropriate explanations.

If a prize assignment is randomized, the selection must be server-side, auditable and consistent with the published promotion rules.

### Prize Types

Admins should be able to configure:

- Free Product
- Fixed-Value Product Credit
- Percentage Discount
- Fixed-Value Coupon
- Special Gift
- Manual Reward

Use WooCommerce products or supported coupon mechanisms where practical.

Define inventory and redemption rules.

### Shortcodes

`[ghr_my_rewards]`

`[ghr_magic_box]`

`[ghr_rewards_summary]`

## 12. Notification System

Build a unified notification service with:

1. In-account notifications.
2. Email notifications.
3. Optional SMS notifications through a configurable gateway.

### Notification Events

Support at minimum:

- Membership Registered
- Membership Activated
- Phone Verification Requested
- Trust Bonus Earned
- Referral Click Recorded (optional aggregate notification)
- Referral Order Confirmed
- Referral Commission Approved
- Referral Wallet Balance Becomes Redeemable
- Trust Seeds Earned
- Trust Seeds Become Redeemable
- Wallet Credit Redeemed
- Wallet Credit Reversed
- Monthly Leaderboard Results Published
- Monthly Prize Won
- Magic Box Assigned
- Magic Box Opened
- Reward Claimed
- Reward Fulfilled
- Reward Expiring
- Important Membership Announcement

### Customer Notification Center

Endpoint:

`/my-account/gh-notifications/`

Display:

- Read / Unread status
- Notification category
- Notification title
- Short message
- Timestamp
- Link to relevant order or reward
- Mark as Read
- Mark All as Read
- Pagination
- User notification preferences

### Header Bell

Shortcode:

`[ghr_notification_bell]`

Display a bell icon with unread count.

On click, show a dropdown containing:

- Latest notifications
- Relative or localized timestamps
- Mark as Read
- Mark All as Read
- View All Notifications

### Admin Notification Settings

Provide editable notification templates with available merge tags such as:

`{member_name}`  
`{member_id}`  
`{order_number}`  
`{reward_amount}`  
`{wallet_balance}`  
`{referral_code}`  
`{reward_name}`  
`{site_name}`

SMS gateway integration must be modular, optional and disabled until configured.

Never hardcode SMS API keys or secrets.

Send emails through supported WordPress/WooCommerce mail infrastructure.

Use background queues for outbound messages rather than blocking checkout.

Prevent duplicate messages when order hooks fire repeatedly.

Keep consent, delivery logs, error states and retry limits.

## 13. Admin Dashboard

Create a top-level WordPress Admin menu:

**Ghoroa Hat Rewards**

Recommended submenus:

- Dashboard
- Members
- Membership Approvals
- Trust Wallet
- Referral Management
- Referral Wallet
- Trust Seeds
- Monthly Leaderboard
- Winners & Magic Boxes
- Notifications
- Reports
- Settings
- Activity & Audit Logs
- System Health

### Admin Overview

Display KPI cards:

- Total Registered Members
- Pending Members
- Active Members
- New Members This Month
- Successful Referral Orders
- Referral Sales Value
- Trust Bonuses Issued
- Referral Commissions Earned
- Trust Seeds Issued
- Redeemed Credits
- Outstanding Reward Liabilities
- Active Monthly Competition
- Pending Rewards
- Notification Delivery Failures

Provide selectable date ranges and export functions.

### Member Management

Admins can:

- Search by Member ID
- Search by Email
- Search by Mobile
- View Member Profile
- View linked WooCommerce Orders
- Verify Membership
- Suspend/Reactivate Membership
- See Wallet Balances
- See Referral History
- See Trust Seeds
- View Rewards
- Add Internal Notes

Member status changes must be logged.

### Wallet Adjustments

Allow authorized admins to:

- Credit Balance
- Debit Balance
- Reverse Incorrect Transactions
- Hold/Release Credits
- View Transaction History

Every adjustment must require:

- Member
- Wallet Type
- Amount
- Reason
- Admin User
- Timestamp
- Unique Adjustment Reference

Do not allow editing or deleting historical ledger transactions.

Corrections must use new reversal or adjustment entries.

### Referral Administration

Provide:

- Referral Link Statistics
- Valid/Invalid Referral Tracking
- Pending Commissions
- Approved Commissions
- Suspected Self-Referrals
- Suspicious Repeated Clicks
- Referral Wallet Liability
- Conversion Performance
- Attribution Settings

### Leaderboard Administration

Allow administrators to:

- Define Monthly Rules
- View Live Rankings
- Review Eligible Members
- Preview Winners
- Finalize Results
- Publish Winner Announcements
- Assign Magic Boxes
- Correct Published Results With Audit Logging

### Reporting

Include downloadable CSV exports for:

- Members
- Monthly Registrations
- Orders Linked to Members
- Referral Sales
- Referral Commissions
- Wallet Transactions
- Wallet Redemptions
- Trust Seeds
- Leaderboard Results
- Prize Fulfillment
- Notification Deliveries
- Outstanding Wallet Liabilities

Reports should support filters and pagination.

## 14. Offline Orders and Member Attribution

Ghoroa Hat also sells products through an offline hub.

The plugin must support in-person orders without creating a separate commerce engine.

Preferred approach:

Use WooCommerce admin-created orders or a compatible WooCommerce POS workflow.

Admin can associate an offline order with an existing member using:

- Email
- Member ID
- Verified Phone Number

If an eligible referral is provided, allow authorized referral attribution before the order is finalized.

Order completion should trigger the same rewards process as online orders.

Do not grant rewards for informal, unrecorded sales.

Guest orders should not automatically create separate membership accounts.

## 15. Database and Ledger Architecture

Use dedicated, properly indexed custom database tables for high-volume loyalty records.

Recommended logical entities:

- Memberships
- Referral Attribution / Visits
- Referral Conversions
- Wallet Accounts
- Wallet Transactions
- Wallet Reservations
- Monthly Score Snapshots
- Monthly Results
- Reward Definitions
- Reward Assignments
- Notifications
- Notification Deliveries
- Audit Logs

The database prefix must be derived from the actual WordPress installation.

Use the WordPress user ID as the customer reference.

Store monetary values using safe fixed-decimal or integer-minor-unit arithmetic, not PHP floating-point calculations.

Every wallet posting needs:

- Unique Transaction ID
- Wallet ID
- Member/User ID
- Transaction Type
- Signed Amount
- Status
- Source Type
- Source Reference
- Idempotency Key
- Timestamp
- Actor / Trigger
- Reversal Reference when applicable

Use database transactions and appropriate locks or atomic updates for balance-changing operations.

Avoid expensive balance recalculations on every page load.

Use indexes, pagination, summary queries and selective caching.

Never allow ledger balances to become inconsistent through repeated processing.

## 16. Frontend UI/UX

The plugin frontend should feel like a premium e-commerce membership portal.

Use Ghoroa Hat branding:

Primary Green: `#087A3D`  
Dark Green: `#075B2A`  
Chilli Red: `#D71920`  
Deep Red: `#A80F16`  
Background: Warm cream / light neutral

Visual components:

- Membership Card
- Wallet Balance Cards
- Compact Header Dropdowns
- Trust Seeds Progress Bar
- Referral Share Card
- Click and Conversion Statistics
- Leaderboard Table
- Winner Cards
- Magic Box Card
- Transaction Timeline
- Notification Bell
- Account Summary Dashboard

Requirements:

- Mobile-first.
- Responsive on tablets and desktops.
- Bengali number and currency formatting options.
- Accessible controls and focus states.
- Loading, empty, error and success states.
- Consistent spacing and typography.
- Scoped CSS to avoid theme/plugin conflicts.
- Compatible with Elementor, Bricks and the block editor.
- No global modification of WooCommerce layout styles.
- No requirement for a frontend JavaScript framework.
- Never enqueue all plugin assets on unrelated site pages.

Shortcode components should work individually and together.

## 17. Security and Privacy

Security is mandatory.

Implement:

- Nonce validation where applicable.
- REST API authentication and permission callbacks.
- Capability-based admin access.
- Strict ownership checks.
- Input sanitization and output escaping.
- Prepared SQL queries.
- Rate limiting for referral tracking and sensitive actions.
- Referral fraud detection.
- Protected financial adjustments.
- Secure verification tokens.
- Privacy-aware public leaderboard identities.
- CSRF and XSS protections.
- No client-controlled balance mutations.
- No public exposure of wallet ledgers.
- No hardcoded API credentials.
- Audit logging of financial and administrative events.
- Personal data export/erasure integration where legally and operationally appropriate.
- Sensible data retention policies.
- Privacy notices and necessary consent controls.

Use WordPress roles and custom capabilities instead of granting all features to ordinary WooCommerce customers.

## 18. Performance and Background Processing

Use WooCommerce Action Scheduler for reliable queued financial processing, monthly tasks and notification delivery.

Required scheduled processes:

- Referral attribution cleanup.
- Expired wallet credit handling where enabled.
- Stale reservation release.
- Pending reward reconciliation.
- Monthly leaderboard score calculation.
- Monthly winner finalization preparation.
- Notification delivery and retries.
- Failed job recovery.
- Reward expiry notices.

The leaderboard may be updated periodically, but wallet financial balances must reflect committed transactions accurately.

Use caching only for safe read-only aggregate data.

Do not cache personalized financial information publicly.

## 19. Admin Settings

Provide separate settings sections for:

**General**

- Enable/Disable Modules
- Membership Settings
- Page Mapping
- Endpoint Slugs
- Branding
- Currency Display
- Timezone

**Trust Wallet**

- Qualified Product
- Introductory Bonus Amount
- Minimum Redemption Order
- One-Time Eligibility
- Expiry Policy

**Referral System**

- Commission Percentage
- Tracking Duration
- Attribution Rule
- Commission Eligibility
- Approval/Hold Period
- Minimum Redemption
- Excluded Products

**Trust Seeds**

- Earning Percentage
- Minimum Redeemable Seeds
- Minimum Order Value
- Maximum Seeds Per Order
- Expiry Policy

**Leaderboard**

- Minimum Monthly Purchase
- Scoring Formulas
- Tie-breaker Rules
- Winner Selection Rules
- Publication Date
- Prize Configuration

**Notifications**

- Email Settings
- SMS Provider Adapter
- Notification Templates
- Queue Settings
- Customer Preferences

**Checkout**

- Enabled Wallets
- Redemption Order
- Stacking Rules
- Minimum Payable Amount
- Refund/Reversal Behavior

**System**

- Database Version
- Background Job Status
- Diagnostics
- Logging
- Safe Reconciliation
- Data Export

All settings need validation, descriptions and safe defaults.

## 20. Implementation Architecture

Use a clean, object-oriented, modular PHP architecture.

Separate:

- Plugin Bootstrap
- Dependency/Compatibility Checks
- Activation/Migration Handler
- Membership Service
- Wallet Ledger Service
- Trust Bonus Service
- Referral Tracking Service
- Referral Commission Service
- Trust Seeds Service
- Checkout Redemption Service
- Leaderboard Service
- Rewards / Magic Box Service
- Notification Service
- Admin Controllers
- Shortcode Controllers
- REST API Controllers
- Background Job Handlers
- Frontend Templates
- Asset Management
- Settings Repository
- Audit Logger

Follow WordPress coding standards and WooCommerce-supported extension patterns.

Do not place the entire system into one PHP file.

All business rules should be implemented through reusable backend services rather than duplicated across shortcode handlers, REST endpoints and WooCommerce hooks.

Provide documented extension hooks and filters for future expansion.

## 21. Installation, Migration and Deactivation

On installation:

1. Validate supported WordPress, PHP and WooCommerce versions.
2. Create/update custom tables through versioned migrations.
3. Register required WooCommerce endpoints.
4. Create missing plugin pages.
5. Save default settings.
6. Register background jobs.
7. Display a first-run setup wizard.
8. Detect and safely enroll existing customers if enabled.

On deactivation:

- Remove scheduled jobs safely.
- Disable plugin routes and services.
- Preserve membership and financial records.
- Do not delete WooCommerce user/order data.

On uninstall:

- Preserve customer financial records by default.
- Provide an explicit, separately confirmed data-removal process.
- Never silently remove wallet history.

## 22. Testing Requirements

The completed plugin must include automated and manual verification for:

- New WooCommerce customers.
- Existing WooCommerce customers.
- Email changes.
- Membership activation.
- Duplicate phone verification.
- First eligible package purchase.
- Repeated package purchase.
- Trust Bonus redemption.
- Referral-link click attribution.
- Guest referred purchases.
- Referral commission approval.
- Self-referral rejection.
- Repeat customer purchases.
- Trust Seeds accumulation.
- Wallet thresholds.
- Concurrent checkout sessions.
- Multiple wallet balances.
- WooCommerce classic and block checkout.
- Cash on Delivery.
- Successful payments.
- Failed payments.
- Partial and full refunds.
- Repeated order hooks.
- Offline admin orders.
- Monthly ranking calculations.
- Tie-breaking.
- Winner finalization.
- Magic Box assignment/opening.
- Notification retries.
- Administrator adjustments.
- User permission restrictions.
- HPOS compatibility.
- Plugin reactivation.
- Database migrations.
- Mobile and accessibility testing.
- Security and performance testing.

No module should be considered complete without relevant test coverage.

## 23. Final Development Deliverables

Provide:

1. Installable WordPress plugin ZIP.
2. Complete readable plugin source code.
3. Database schema and migration routines.
4. Automatic page and endpoint setup.
5. Frontend shortcode library.
6. WooCommerce checkout integration.
7. Complete admin dashboard.
8. Settings and management controls.
9. Background processing jobs.
10. REST API documentation.
11. User and administrator documentation.
12. Test suite and test results.
13. Installation and upgrade instructions.
14. Versioned changelog.

Development must proceed module by module, with stable interfaces between modules.

Do not implement unconfirmed business decisions silently.

Where the requirements contain an unresolved choice, expose a safe configurable setting and document the selected default.

The final result must be a professionally engineered WooCommerce membership and rewards plugin, suitable for reliable real-world use and future expansion.

**Do not begin coding until the technical architecture, database design, route map, financial rules and phased development plan have been reviewed and approved.**