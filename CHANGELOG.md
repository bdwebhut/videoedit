# Changelog

## 1.0.0-rc.2 — 2026-10-16

- Restyled account components to follow the supplied theme reference: white cards, subtle gray borders, compact headings, green icons and actions.
- Replaced the full green membership card with a compact profile, status badge, public ID and localized dates.
- Replaced dashboard summary tables with readable balance cards and monthly metrics; grouped referrals, verification, rewards and notifications into responsive panels.
- Added Bengali table/navigation/preference labels and clearer reward empty states.
- Scoped component CSS preserves the host theme header, welcome banner, sidebar and WooCommerce layout.
- Retains existing financial rules, schema, ledger and checkout interfaces. See docs/DESIGN-REFRESH.md for validation and previews.

## 1.0.0-rc.1 — 2026-10-16

Initial release candidate implementing the approved Ghoroa Hat design:

- Permanent user-ID memberships, email/phone verification and audited identity reconciliation.
- Three transactional ledgers, reservations, expiry, reversals, reward debt and explicit late-payment settlement review.
- Trust package entitlement, personal Seeds and configurable referral qualification/approval.
- Native classic and Checkout Blocks credit integration, order policy snapshots and cumulative refund reconciliation.
- Site-calendar monthly scoring, versioned reviewed results and deterministic unique award slots.
- Predefined owner-only Magic Boxes, native prize coupons and fulfillment tracking.
- Bengali frontend components, configurable My Account routes, 22 shortcodes and notification preferences.
- Capability-controlled administration, filtered CSV exports, privacy tools, background recovery and safe lifecycle handling.

Schema version: 1. No earlier released schema is supported by this initial version. Future upgrades must introduce a new schema version and forward migration.
