# Database schema and migrations

Authoritative schema: `Installation::schema()`. Actual names use `$wpdb->prefix . "ghr_"`; every table uses InnoDB and WordPress charset/collation. IDs reference WordPress users and native WooCommerce orders logically; no email/phone foreign keys or order-storage SQL is used. Financial relations use application-controlled locks rather than database cascades.

Schema version 1 is installed/upgraded through `Installation::migrate()` and `dbDelta()`. It verifies table engines and stores `ghr_db_version` only after successful checks. Future releases must introduce new schema versions. No prior released schema exists.

## ghr_memberships

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
user_id bigint unsigned NOT NULL,
member_id varchar(40) DEFAULT NULL,
referral_code varchar(32) NOT NULL,
status varchar(16) NOT NULL DEFAULT 'pending',
phone varchar(20) NOT NULL DEFAULT '',
verified_phone varchar(20) DEFAULT NULL,
email_verified_at datetime DEFAULT NULL,
registered_at datetime NOT NULL,
activated_at datetime DEFAULT NULL,
public_consent tinyint NOT NULL DEFAULT 0,
notes text,
PRIMARY KEY  (id),
UNIQUE KEY user_id (user_id),
UNIQUE KEY member_id (member_id),
UNIQUE KEY referral_code (referral_code),
UNIQUE KEY verified_phone (verified_phone),
KEY status (status),
KEY registered_at (registered_at)
```

## ghr_identity_verifications

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
user_id bigint unsigned NOT NULL,
purpose varchar(16) NOT NULL,
target varchar(191) NOT NULL,
token_hash char(64) NOT NULL,
attempts int NOT NULL DEFAULT 0,
expires_at datetime NOT NULL,
consumed_at datetime DEFAULT NULL,
PRIMARY KEY  (id),
UNIQUE KEY token_hash (token_hash),
KEY owner_expiry (user_id,purpose,expires_at)
```

## ghr_membership_events

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
user_id bigint unsigned NOT NULL,
event varchar(40) NOT NULL,
reference varchar(191) NOT NULL,
order_id bigint unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY reference (reference),
KEY user_date (user_id,created_at)
```

## ghr_wallet_accounts

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
user_id bigint unsigned NOT NULL,
wallet varchar(16) NOT NULL,
posted bigint NOT NULL DEFAULT 0,
reserved bigint NOT NULL DEFAULT 0,
held bigint NOT NULL DEFAULT 0,
earned bigint NOT NULL DEFAULT 0,
redeemed bigint NOT NULL DEFAULT 0,
reversed bigint NOT NULL DEFAULT 0,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY owner_wallet (user_id,wallet)
```

## ghr_wallet_transactions

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
account_id bigint unsigned NOT NULL,
user_id bigint unsigned NOT NULL,
wallet varchar(16) NOT NULL,
kind varchar(32) NOT NULL,
amount bigint NOT NULL,
status varchar(20) NOT NULL DEFAULT 'committed',
source_type varchar(32) NOT NULL,
source_ref varchar(191) NOT NULL,
order_id bigint unsigned NOT NULL DEFAULT 0,
order_item_id bigint unsigned NOT NULL DEFAULT 0,
idempotency_key varchar(191) NOT NULL,
actor bigint unsigned NOT NULL DEFAULT 0,
reversal_of bigint unsigned DEFAULT NULL,
reason text,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY idempotency_key (idempotency_key),
KEY account_date (account_id,created_at),
KEY owner_wallet (user_id,wallet,id),
KEY owner_history (user_id,id),
KEY order_id (order_id),
KEY source_ref (source_type,source_ref),
KEY reversal_of (reversal_of)
```

## ghr_credit_lots

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
transaction_id bigint unsigned NOT NULL,
account_id bigint unsigned NOT NULL,
original bigint NOT NULL,
remaining bigint NOT NULL,
expired bigint NOT NULL DEFAULT 0,
debt_repaid bigint NOT NULL DEFAULT 0,
expires_at datetime DEFAULT NULL,
PRIMARY KEY  (id),
UNIQUE KEY transaction_id (transaction_id),
KEY account_expiry (account_id,expires_at)
```

## ghr_credit_allocations

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
lot_id bigint unsigned NOT NULL,
reservation_id varchar(64) NOT NULL,
units bigint NOT NULL,
state varchar(16) NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY allocation (lot_id,reservation_id),
KEY reservation_id (reservation_id)
```

## ghr_wallet_reservations

```sql
id varchar(64) NOT NULL,
account_id bigint unsigned NOT NULL,
user_id bigint unsigned NOT NULL,
wallet varchar(16) NOT NULL,
intent_hash char(64) NOT NULL,
cart_hash char(64) NOT NULL,
order_id bigint unsigned NOT NULL DEFAULT 0,
units bigint NOT NULL,
state varchar(20) NOT NULL,
expires_at datetime NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY account_state (account_id,state),
KEY expiry (state,expires_at),
KEY order_id (order_id)
```

## ghr_reward_entitlements

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
user_id bigint unsigned NOT NULL,
benefit varchar(32) NOT NULL,
scope varchar(32) NOT NULL,
revoked tinyint NOT NULL DEFAULT 0,
order_id bigint unsigned NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY entitlement (user_id,benefit,scope),
KEY order_id (order_id)
```

## ghr_order_effects

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
order_id bigint unsigned NOT NULL,
effect_key varchar(64) NOT NULL,
user_id bigint unsigned NOT NULL,
wallet varchar(16) NOT NULL,
desired bigint NOT NULL DEFAULT 0,
posted bigint NOT NULL DEFAULT 0,
eligible bigint NOT NULL DEFAULT 0,
revision int NOT NULL DEFAULT 0,
policy longtext NOT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY order_effect (order_id,effect_key),
KEY user_date (user_id,created_at),
KEY period (effect_key,created_at,user_id)
```

## ghr_referral_visits

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
referrer_id bigint unsigned NOT NULL,
visitor_hash char(64) NOT NULL,
flags varchar(100) NOT NULL DEFAULT '',
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY referrer_date (referrer_id,created_at),
KEY visitor_date (visitor_hash,created_at)
```

## ghr_referral_attributions

```sql
id varchar(64) NOT NULL,
referrer_id bigint unsigned NOT NULL,
customer_id bigint unsigned NOT NULL DEFAULT 0,
visit_id bigint unsigned NOT NULL,
expires_at datetime NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY customer_expiry (customer_id,expires_at),
KEY referrer_id (referrer_id)
```

## ghr_referral_conversions

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
order_id bigint unsigned NOT NULL,
buyer_id bigint unsigned NOT NULL DEFAULT 0,
referrer_id bigint unsigned NOT NULL,
eligible bigint NOT NULL DEFAULT 0,
commission bigint NOT NULL DEFAULT 0,
status varchar(24) NOT NULL,
approved tinyint NOT NULL DEFAULT 0,
release_at datetime NOT NULL,
qualified_at datetime DEFAULT NULL,
flags varchar(191) NOT NULL DEFAULT '',
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY order_id (order_id),
KEY referrer_state (referrer_id,status,created_at),
KEY release_at (release_at),
KEY period (status,created_at,referrer_id)
```

## ghr_monthly_scores

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
month char(7) NOT NULL,
user_id bigint unsigned NOT NULL,
rules_version char(64) NOT NULL,
personal bigint NOT NULL DEFAULT 0,
orders_count int NOT NULL DEFAULT 0,
conversions int NOT NULL DEFAULT 0,
referral_value bigint NOT NULL DEFAULT 0,
seeds bigint NOT NULL DEFAULT 0,
first_at datetime DEFAULT NULL,
first_seeds_at datetime DEFAULT NULL,
first_referral_at datetime DEFAULT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY month_user (month,user_id,rules_version),
KEY month_personal (month,personal)
```

## ghr_monthly_results

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
month char(7) NOT NULL,
version int NOT NULL,
rules longtext NOT NULL,
entries longtext NOT NULL,
digest char(64) NOT NULL,
supersedes_id bigint unsigned DEFAULT NULL,
actor bigint unsigned NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY month_version (month,version)
```

## ghr_result_publications

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
snapshot_id bigint unsigned NOT NULL,
action varchar(24) NOT NULL,
reason text,
actor bigint unsigned NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY snapshot_action (snapshot_id,action)
```

## ghr_reward_definitions

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
name varchar(191) NOT NULL,
type varchar(32) NOT NULL,
config longtext NOT NULL,
stock int NOT NULL DEFAULT 0,
active tinyint NOT NULL DEFAULT 1,
PRIMARY KEY  (id),
KEY active (active)
```

## ghr_reward_campaigns

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
name varchar(191) NOT NULL,
month char(7) NOT NULL,
category varchar(32) NOT NULL,
definition_id bigint unsigned NOT NULL,
capacity int NOT NULL,
assigned int NOT NULL DEFAULT 0,
expires_at datetime DEFAULT NULL,
PRIMARY KEY  (id),
KEY month_category (month,category)
```

## ghr_reward_assignments

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
box_id varchar(64) NOT NULL,
user_id bigint unsigned NOT NULL,
campaign_id bigint unsigned NOT NULL,
slot varchar(191) NOT NULL,
definition_id bigint unsigned NOT NULL,
reward_snapshot longtext NOT NULL,
state varchar(20) NOT NULL DEFAULT 'unopened',
assigned_at datetime NOT NULL,
opened_at datetime DEFAULT NULL,
claimed_at datetime DEFAULT NULL,
fulfilled_at datetime DEFAULT NULL,
expires_at datetime DEFAULT NULL,
delivery_status varchar(40) NOT NULL DEFAULT 'pending',
coupon_id bigint unsigned NOT NULL DEFAULT 0,
explanation text,
PRIMARY KEY  (id),
UNIQUE KEY box_id (box_id),
UNIQUE KEY slot (slot),
KEY owner_state (user_id,state),
KEY expires_at (expires_at)
```

## ghr_notifications

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
user_id bigint unsigned NOT NULL,
event_key varchar(191) NOT NULL,
category varchar(64) NOT NULL,
title varchar(191) NOT NULL,
message text NOT NULL,
link text,
read_at datetime DEFAULT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY owner_event (user_id,event_key),
KEY owner_read (user_id,read_at,created_at)
```

## ghr_notification_deliveries

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
notification_id bigint unsigned NOT NULL,
channel varchar(16) NOT NULL,
state varchar(20) NOT NULL DEFAULT 'pending',
attempts int NOT NULL DEFAULT 0,
next_attempt datetime NOT NULL,
last_error varchar(191) NOT NULL DEFAULT '',
PRIMARY KEY  (id),
UNIQUE KEY notification_channel (notification_id,channel),
KEY due (state,next_attempt)
```

## ghr_notification_preferences

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
user_id bigint unsigned NOT NULL,
preferences longtext NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY user_id (user_id)
```

## ghr_audit_logs

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
actor bigint unsigned NOT NULL DEFAULT 0,
operation varchar(64) NOT NULL,
subject varchar(191) NOT NULL,
details longtext NOT NULL,
reason text,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY subject_date (subject,created_at),
KEY actor_date (actor,created_at)
```

## ghr_reconciliation_cases

```sql
id bigint unsigned NOT NULL AUTO_INCREMENT,
source_id bigint unsigned NOT NULL,
target_id bigint unsigned NOT NULL,
evidence text NOT NULL,
plan longtext NOT NULL,
reviewer bigint unsigned NOT NULL,
state varchar(20) NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY state_date (state,created_at)
```

## Other persistent data

Options store validated settings, page mappings, schema version, persistent member-ID sequence, financial epoch, month rules, score timestamps, encrypted SMS credential, templates, recovery/rate state and timed removal tickets. Protected user metadata stores canonical reconciliation relationships. WooCommerce CRUD metadata stores policy/item snapshots, attribution and reservation/review references; orders stay in WooCommerce HPOS or legacy storage. WordPress pages/endpoints are mapped by IDs rather than hardcoded account URLs.
