# Extension points

Use a separate site plugin; do not edit the rewards plugin. Preserve financial invariants, capabilities and idempotency keys.

- `ghr_product_eligible` filter: `(bool $eligible, WC_Product $product, array $policy) -> bool`. Further restrict product eligibility. Order-line eligibility is snapshotted, so changes affect new policy snapshots rather than rewriting prior orders.
- `ghr_tracking_consent` filter: `(bool $consented) -> bool`. Integrate your consent platform. WordPress Consent API marketing consent is recognized when available. Do not return true without the site's approved consent policy.
- `ghr_sms_adapter` filter: `(null|callable $adapter) -> callable`. Adapter receives normalized destination, message and idempotency key and returns true or throws on failure. Built-in configurable JSON adapter is registered when configured. Honor idempotency and never log credentials or codes.

Example optional adapter:

```php
add_filter('ghr_sms_adapter', static function ($adapter) {
    return static function ($phone, $message, $key) {
        // Send via your provider with $key as its idempotency key.
        // Throw a RuntimeException on transient/permanent delivery failure.
        return my_store_sms_send($phone, $message, $key);
    };
}, 20);
```

Background hooks are operational entry points, not authorization shortcuts: `ghr_order_reconcile(order_id)`, `ghr_score_refresh(month)`, `ghr_maintenance`, `ghr_monthly`, `ghr_notification_delivery(delivery_id)`, `ghr_verification_delivery(job_id)`, `ghr_enroll_batch(page)`, `ghr_broadcast(event,reference,cursor)`, `ghr_recovery_batch(cursor)`. Schedule through WooCommerce Action Scheduler group `ghr`; avoid duplicate custom schedulers.

Use WooCommerce CRUD for order/product/coupon integration. Do not directly update cached accounts, immutable ledger entries, order storage tables or sealed prize snapshots. Future business-rule plugins should call the shared services inside an authenticated staff workflow and supply stable idempotency keys.
