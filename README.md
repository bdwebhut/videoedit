# Ghoroa Hat Smart Membership & Rewards

Version **1.0.0-rc.1** · BD WEBHUT PRIVATE LIMITED · GPL-2.0-or-later

Download the [installable plugin ZIP](dist/ghoroa-hat-rewards-1.0.0-rc.1.zip) or the [complete source archive](dist/ghoroa-hat-rewards-1.0.0-rc.1-source.zip). Open the file and use GitHub’s **Download raw file** button. Checksums are in [SHA256SUMS.txt](dist/SHA256SUMS.txt).

WordPress/WooCommerce plugin with permanent memberships, separate Trust/Referral/Seeds wallets, native checkout credits, referral attribution, reviewed monthly awards, predefined Magic Boxes, notifications, reporting and audit records.

Requires WordPress 6.7+, WooCommerce 9.6+, 64-bit PHP 8.2+, OpenSSL and transactional InnoDB tables. The initial financial implementation supports BDT with two decimal places. No Composer, npm, external JavaScript CDN or separate runtime service is required.

- [Install, configure and upgrade](docs/INSTALLATION.md)
- [Administrator guide](docs/ADMINISTRATOR.md)
- [Member guide and shortcodes](docs/USER-GUIDE.md)
- [Financial policies and architecture](docs/ARCHITECTURE.md)
- [Database schema](docs/SCHEMA.md)
- [REST API](docs/REST-API.md)
- [Extension points](docs/EXTENSIONS.md)
- [Testing instructions](docs/TESTING.md) and [results](docs/TEST-RESULTS.md)
- [Requirement coverage and deployment acceptance](docs/REQUIREMENTS.md)

Use the installable ZIP for WordPress upload. The source distribution additionally includes automated tests and the coding-standard configuration. Tests create synthetic customers and orders: run them only in isolated test databases.
