# Shi3ld development plan

Source: ../web-scope.md, especially sections 6–10 and 20.

## Architecture decisions

Magento Open Source 2.4.9 and Laravel 13 use PHP 8.5 in separate containers built from the same runtime. Composer 2.10, MySQL 8.4, OpenSearch 3, Valkey 9 and nginx 1.30 follow Magento's current compatibility matrix. Composer lock files pin application dependencies after installation. Docker minor version tags receive patch updates on rebuild; pin image digests before staging.

Magento owns catalogue, variants, stock, ecommerce customers, orders and public CMS pages. Laravel owns cleaning enquiries, quotes and eventual operations. Database migration commands never cross these boundaries. Laravel's Magento reporting credentials have SELECT only, with no schema permissions. APIs are the primary integration path. Reporting reads are optional and must not become a checkout or booking dependency.

Separate cache instances isolate Magento cache flushes from Laravel queues. Both databases are on the same MySQL server, so database/server failures remain shared. Development nginx publishes HTTPS on port 443; databases/caches remain internal and Mailpit stays bound to localhost.

## Step 1: development foundation

- Docker services, separate schemas/users, PHP 8.5 and local email.
- Official Laravel scaffold, queue and scheduler.
- Official Magento installation using local Marketplace credentials.
- Shi3ld_Catalog, Shi3ld_Content and Shi3ld_Integration Magento modules, Shi3ld/Alliance theme.
- Read-only reporting connection and API client in Laravel.

## Step 2: cleaning enquiry milestone

- Domestic, commercial and care-home landing content in Magento CMS.
- Laravel quote form with validation, conditional organisation/site details and unique references.
- Enquiry persistence and queued internal/customer emails.
- Staff enquiry access requires authenticated, authorised operations users in the next release.

## Step 3: ecommerce milestone

- Confirm actual launch product CSV, images, SKUs, prices, colour variants, stock and lead times.
- Configure categories, attributes and search.
- Build and activate custom theme after Magento installation.
- Configure payment sandbox, VAT and agreed shipping rules.
- Validate basket, guest checkout, account flow and transactional email.

## Step 4: launch quality and content

- Approved branding/content, trust evidence, legal policies, service areas.
- Accessibility target WCAG 2.2 AA; keyboard and assistive technology validation.
- SEO, analytics with consent, staging, backups and restore checks.
- Payment/enquiry acceptance and client UAT before production.

## Step 5: Laravel operations

- Authorised operations users, customer/site records, quotes, booking calendar and cleaner availability.
- Assignment, recurring schedules, reminders and operations dashboard.
- Customer/cleaner portals, job completion and invoicing integrations.
- AI only after deterministic scheduling rules exist.

## Integration contract

Version events (e.g. customer.updated.v1); include unique event ID, timestamp and entity ID. Magento writes integration outbox rows within the transaction associated with the change; the dispatcher retries delivery. Laravel deduplicates by event ID. Track retry counts and dead letters; reconcile records periodically using API reads. This contract is planned; event capture and dispatch are not yet implemented.

Use Magento internal customer/order IDs as references, plus display order number and SKU where needed. No cross-database foreign keys, shared password tables or automatic SSO. Use a dedicated identity integration when that milestone is agreed.

## Inputs still required

Approved product data/images; logo/brand; company contacts; service areas; payment/shipping choices; VAT treatment; approved policies and evidenced trust statements. No real product prices or compliance claims should be invented.

## Milestone status — 7 October 2026

Completed: development images; isolated databases/users; Laravel 13.35.0 installation and migrations; quote form/controller; queued notifications; reporting connection; Laravel workers/scheduler; local nginx/Mailpit preview.

Completed on 8 October: Magento 2.4.9 installation; catalogue lead-time attribute; domestic/commercial/care-home CMS patches; integration outbox schema and whitelist; initial theme activation; all 14 indexers; Magento cron; native SMTP to Mailpit; official VULN-39341 hotfix and repeatable Composer hooks.

Not implemented yet: product catalogue import, payment/shipping configuration, staff portal, bookings, event dispatcher/webhooks, reconciliation, shared authentication and production launch requirements. Magento schema/module checks and shop/domestic-page HTTP checks passed. Full functional acceptance remains pending product, payment and shipping implementation.

## Services site update — 12 October 2026

The user requested the public services site in Laravel at the eventual production path `www.shi3ldalliance.co.uk/services`, with development preview first and sample content for now. This updates the original Magento-CMS ownership for these service pages: Laravel now owns the public `/services` area and its editable service-page data; Magento owns commerce and the remaining public shop content.

Implemented: responsive public services site, 16 editable service pages, structured enquiry journey, separate authorised staff login, enquiry inbox/filter/status/notes/CSV export, page content editing/publishing, service sitemap/metadata, and persistent notification delivery with scheduled retries. Preview: https://shi3ld.rpidevserver/services . Production has not been deployed. Original Phase 2 bookings, workforce/portals, payments and AI remain future work.

## Combined homepage integration — 12 October 2026

Magento now owns `/`, `/shop` and `/about`, with editable CMS blocks and the shared Shi3ld theme. Laravel owns `/services` and service enquiries/staff operations. Legacy Magento cleaning URLs redirect to Laravel; Laravel's former about URL redirects to Magento. Root sitemap indexing covers both frameworks. Development previews use sample content and noindex metadata.

Staff enquiry access is implemented. Product catalogue import, checkout configuration, Phase 2 calendar/customer profiles, shared authentication and event delivery remain pending. Database ownership is unchanged: Magento schema/data patches and Laravel migrations remain separate.
