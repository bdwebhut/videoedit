# Testing

Tests mutate options and create synthetic users, products, orders, refunds, rewards and financial history. Run only on isolated disposable WordPress installations with WooCommerce and the plugin activated. Never run fixtures against production.

## Automated suites

Copy/mount source including `tests` into `wp-content/plugins/ghoroa-hat-rewards`. With PHP/WP-CLI available:

```sh
php wp-content/plugins/ghoroa-hat-rewards/tests/unit.php
wp eval-file wp-content/plugins/ghoroa-hat-rewards/tests/integration.php
```

Other eval-file suites: checkout, advanced, referrals, expiry, competition, reconciliation, shortcodes, sms, lifecycle. The integration suite creates a context used by advanced tests, so run the provided sequential runner. In Docker:

```sh
python3 tests/run-suite.py --container ghr-wp --output /tmp/ghr-results
python3 tests/run-concurrency.py
python3 tests/reactivation.py ghr-wp-min
wp eval-file wp-content/plugins/ghoroa-hat-rewards/tests/performance.php
```

The concurrency runner expects the `ghr-wp` fixture container; it launches independent database workers against the same balance and asserts one spend succeeds and one is rejected. The suite runner records actual platform versions, assertion counts, timings and warnings. Do not run setting-mutating suites concurrently against one database. SMS HTTP is mocked and all message destinations are synthetic. Tests generally suppress mail; background jobs can still attempt local delivery, so disable real outbound email/SMS in test environments.

## Browser checks

`tests/browser/e2e.cjs` requires Node, Chromium, playwright-core and @axe-core/playwright. It creates fixture customers/products on the local test site and performs both actual native COD checkouts, visits all account/admin screens, checks JS errors, narrow mobile overflow and automated accessibility. Configure the local container and credentials referenced by the script; these are local fixture defaults, never deployment credentials. Install dependencies outside the production plugin.

## Static checks

PHP lint all `.php` files, Node syntax-check all assets. `phpcs.xml.dist` uses WordPress-Core; reviewed per-line SQL-fragment exceptions identify trusted prefixes/allowlisted identifiers and prepared subqueries. Run PHP_CodeSniffer with WordPress Coding Standards installed. This is not a substitute for an independent security review or production gateway certification.

## Deployment acceptance still required

Use the intended theme, Elementor/Bricks versions if used, paid payment gateway sandbox and real mail/SMS provider test accounts. Verify inbox/SMS receipt, provider retries, taxes/shipping, POS integration, cache exclusions for authenticated routes, cron reliability, backup/restore, manual keyboard/screen-reader operation and expected traffic. Synthetic aggregation timing is a bounded local benchmark, not real-store capacity certification.

## Packaged lifecycle test

`package-lifecycle.py` is deliberately restricted to the disposable `ghr-wp-install` container and the exact `Ghoroa Hat Package Smoke` site title. It performs real uninstall/reinstall and explicitly armed data removal. It expects the packaged ZIP mounted under `/release` and WP-CLI under `/fixtures/wp-cli.phar`. Never adapt this destructive fixture to a real store. The release evidence records its observed output.
