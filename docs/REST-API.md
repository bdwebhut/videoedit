# REST API

Base: `/wp-json/ghr/v1`. JSON responses use WordPress REST conventions. Validation errors return `ghr_validation` HTTP 400, permission errors use WordPress 401/403, unexpected failures return `ghr_failure` HTTP 500 with protected log context. Success responses are private/no-store. Public rankings contain permanent public member IDs and scores; winner display identities require consent.

Owner routes require a logged-in WordPress cookie and `X-WP-Nonce` generated with `wp_create_nonce('wp_rest')`, including reads. Application-password-only automation is not a supported replacement for this cookie/nonce contract. The server always derives owner ID from the current user.

| Method | Route | Input / response |
|---|---|---|
| GET | `/me` | Own membership and notification preferences |
| GET | `/me/wallets` | Separate cached trust/referral/seeds summaries |
| GET | `/me/transactions` | wallet, page; kind/status/direction/from/to filters; 20 rows, UTC timestamps |
| GET | `/me/referrals` | page; pseudonymous activity and aggregates |
| GET | `/me/rewards` | page; prize hidden while sealed |
| GET | `/me/notifications` | page; items and unread count |
| POST | `/membership/enroll` | Idempotent enrollment of existing logged-in user |
| POST | `/verification/request` | purpose email/phone, target |
| POST | `/verification/confirm` | purpose, token |
| GET | `/checkout` | Current server-derived eligibility and balances |
| POST | `/checkout/reservations` | wallet, units, intent; units is integer paisa or whole Seeds |
| DELETE | `/checkout/reservations/{id}` | Owner-only opaque reservation release |
| POST | `/rewards/{id}/open` | Idempotent reveal |
| POST | `/rewards/{id}/claim` | Owner-only active/verified claim |
| POST | `/notifications/{id}/read` | Owner notification |
| POST | `/notifications/read-all` | Owner notifications |
| POST | `/me/preferences` | Validated channel/consent preferences |
| GET | `/leaderboard` | Public; month YYYY-MM, category regular/referrals/seeds, limit |
| GET | `/winners` | Public; optional month |
| GET | `/admin/{resource}` | members, transactions, commissions, results, campaigns; page; resource-specific capability |
| GET | `/admin/health` | `ghr_manage_settings` |
| POST | `/admin/commands/{command}` | JSON command; nonce plus dedicated capability |

Administrative command groups (see `Rest::command()` for exact validation and fields):

- `member-status`, `member-note`, `enroll-existing`: member management.
- `verify-phone`: identity evidence and verified normalized phone.
- `adjust`: user_id, wallet, signed integer amount, reference, reason. `reverse`: transaction_id, reference, reason. `hold`: user_id, wallet, signed integer delta, reason.
- `late-settlement`: order_id, reason; optional debt_approval exact `ALLOW REWARD DEBT`.
- `approve`: conversion_id, reason. `reconcile-order`: order_id. `refund-review`: order_id, refund_id, allocations JSON of item IDs to product refund paisa, reason.
- `month-rules`: month, supported integer weights/minimum, tie_breaker, reason. `prepare`: month. `finalize`: month, reason. `publish`: snapshot_id, reason.
- `definition`: name, type, stock, supported product_id/value/expiry_days. `campaign`: name, month, category, definition_id, capacity, optional expires_at. `assign`: user_id, campaign_id, reference. `fulfill`/`revoke`: user_id, reward_id, reason.
- `announcement`: user_id (0 broadcasts), reference, message. `retry-delivery`: delivery_id, reason.
- `reconcile-preview`: source_id, target_id, reason. `reconcile-execute`: case_id, exact preview confirmation.
- `removal-preview`/`removal-confirm`: privileged timed explicit removal protocol. `maintenance`: bounded maintenance.

Example browser request (administrator session):

```js
fetch('/wp-json/ghr/v1/admin/commands/adjust', {
  method: 'POST', credentials: 'same-origin',
  headers: {'Content-Type': 'application/json', 'X-WP-Nonce': nonce},
  body: JSON.stringify({user_id: 123, wallet: 'trust', amount: 10000,
    reference: 'support-case-123-credit', reason: 'Approved goodwill credit'})
});
```

References are idempotency keys, not arbitrary reusable labels. Customer interfaces must use returned eligibility and never calculate an authoritative wallet balance. Checkout Blocks uses the WooCommerce Store API extension update callback registered by `Checkout`; do not bypass it with gateway adjustments.
