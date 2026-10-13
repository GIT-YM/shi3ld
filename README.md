# Shi3ld Alliance

Magento commerce/public website and Laravel cleaning services, based on [web-scope.md](web-scope.md).

Development stack: PHP 8.5 for both apps, Magento Open Source 2.4.9, Laravel 13, Composer 2.10, MySQL 8.4, OpenSearch 3, separate Valkey 9 instances, nginx 1.30 and Mailpit.

## Start development

1. Run `make env` to create local random credentials. `.env` and `.secrets/` are ignored by Git.
2. Install mkcert, trust its local CA with `mkcert -install`, run `./bin/generate-development-cert`, then run `make build`. This server uses the same CA as CZ.
3. Run `docker compose up -d db laravel-cache mailpit`.
4. Run `./bin/bootstrap-laravel` to install Laravel dependencies, generate its key and migrate its own database. Existing installs keep their existing application key; run the key command only for a new installation.
5. Generate Magento Marketplace access keys at https://commercemarketplace.adobe.com/customer/accessKeys/ . Put them in `.secrets/composer-auth.json`:

   ```json
   {"http-basic":{"repo.magento.com":{"username":"PUBLIC_KEY","password":"PRIVATE_KEY"}}}
   ```

   Keep this file private. It is mounted only into the Magento Composer tooling container.
6. Run `./bin/bootstrap-magento` to download the official release, install Magento, enable project modules and configure search/cache.
7. Run `make up`, then `make workers` once both apps are installed and migrated.

Homepage: https://shi3ld.rpidevserver . Shop: https://shi3ld.rpidevserver/shop . Services: https://shi3ld.rpidevserver/services . Admin: https://shi3ld.rpidevserver/admin/ . HTTPS is published on port 443, with HTTP on port 80 redirecting to HTTPS. The old localhost ports 9080/9082 also redirect. Mailpit remains local at http://localhost:9085.

On your browsing computer, point both hostnames to this server using your local DNS server or hosts file:

```text
192.168.1.200 shi3ld.rpidevserver services.shi3ld.rpidevserver
```

Windows hosts file: `C:\Windows\System32\drivers\etc\hosts` (edit as Administrator). Linux/macOS: `/etc/hosts`. If CZ's SSL certificate is already trusted, this certificate uses the same CA. Otherwise, import this server's public `/home/kashw/.local/share/mkcert/rootCA.pem` into your computer's trusted certificate authorities. Never copy the CA private key. Certificates and private keys are ignored by Git. The server address is currently assigned by DHCP; reserve it on your router to keep this mapping stable.

`./bin/configure-development-urls` applies Magento's secure storefront/admin URLs and clears the Laravel configuration cache. Laravel publishes secure session cookies. URL values are in root `.env`; hostname changes also require updating nginx and regenerating the certificate.

Magento admin user/password are in local `.env`; don't paste credentials into tickets. Two-factor authentication is temporarily disabled for development at the user's request (`Magento_TwoFactorAuth` and `Magento_AdminAdobeImsTwoFactorAuth`).

`make migrate` runs Magento's `setup:upgrade` and Laravel migrations independently. `make seed` seeds Laravel only. Magento reference data lives in data patches; production records and development demo records must be separated. Never run destructive reset commands against production. Take separate database snapshots before schema changes and coordinate restore points if integrations depend on both databases.

MySQL initialization runs only when its volume is empty. Editing `.env` later does not rotate existing database passwords. Never delete volumes merely to fix a configuration change.

Magento's official bundled nginx configuration is installed by the bootstrap script; the initial shop placeholder returns 503 until installation completes. Laravel runs independently while Magento keys are pending. Workers are optional until application installs complete.

Architecture, milestones and remaining client inputs: [development plan](docs/development-plan.md).

Official compatibility references: [Magento requirements](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements), [Magento release](https://experienceleague.adobe.com/en/docs/commerce-operations/release/notes/magento-open-source/2-4-9), [Laravel releases](https://laravel.com/docs/13.x/releases).

## Current milestone

Laravel 13.35.0 is installed with PHP 8.5.11 and Composer 2.10.3. Its MySQL migrations have run. The services quote form is available at `/services/quote`, with conditional fields, enquiry persistence, unique references, CSRF protection, rate limiting and persistent customer/internal notification tracking. Laravel queue and scheduler containers run independently of Magento.

Magento Open Source 2.4.9 is installed. Shi3ld_Catalog, Shi3ld_Content and Shi3ld_Integration are enabled, their data/schema patches have run, and the Shi3ld Alliance theme is activated. All 14 indexers were rebuilt and Magento reports its schema up to date. The outbox schema is a foundation; event capture, dispatcher retries, webhook authentication, deduplication and reconciliation remain future work. The Magento API client is prepared; authenticated API integration remains a later milestone.

Before public launch, approve the privacy policy/retention rules, company contacts, branding, service coverage, quotation process and staff access. Customer/internal notification delivery is retried by the scheduler using persisted timestamps. Delivery is at least once: a process crash after SMTP accepts a message but before the timestamp is saved can cause a repeat acknowledgement. Outbound emails currently go to Mailpit.

To run only the Laravel services:

```bash
docker compose up -d db laravel-cache mailpit
docker compose up -d --no-deps php-laravel nginx
docker compose --profile workers up -d laravel-queue laravel-scheduler
```

The combined homepage and shop are served by Magento; domestic cleaning is available at `/services/domestic-cleaning` in Laravel. `make up` starts the full stack; `make workers` starts Magento cron and Laravel queue/scheduler services.

Runtime PHP containers mount only their own application directories. The nginx container mounts Magento and Laravel public files without root credentials. Only temporary installation tooling mounts the repository root. This keeps Laravel runtime code from reading Magento write credentials in root `.env`.

## Magento installation details

Magento admin: https://shi3ld.rpidevserver/admin/ . Admin credentials remain in the local root `.env`, and two-factor authentication is temporarily disabled for development. Mailpit captures development messages at http://localhost:9085 . Native SMTP settings are stored in Magento's ignored `app/etc/env.php` using `bin/configure-magento-development`.

MySQL runs with `log_bin_trust_function_creators=1`, which permits Magento's indexing triggers without granting its database account global SUPER privileges. The installer checks the completed installation date so an interrupted install can resume without deleting its database.

Adobe's [VULN-39341 hotfix](https://experienceleague.adobe.com/en/docs/commerce-knowledge-base/kb/announcements/commerce-apsb26-146) is applied from `magento/patches/VULN-39341_249.patch`. Composer hooks run `magento/bin/shi3ld-apply-patches.php` after install/update; it checks for an already applied patch and stops if source files do not match. Review this version-specific patch when upgrading Magento.

Smoke checks completed: shop, domestic cleaning page and Laravel quote page return HTTP 200; Magento schema is up to date; all three Shi3ld modules are enabled; theme ID 4 is active. This is the development foundation. Product import, payment/shipping settings, final page design and launch acceptance remain in the delivery plan.

## Laravel services preview — 12 October 2026

The public services site now runs at **https://shi3ld.rpidevserver/services**, using the same hostname as Magento. Nginx sends `/services` and its descendants to Laravel and serves `/services/assets/` from Laravel public files. Magento continues to serve the shop and `/admin/`. The previous services subdomain redirects to this path.

The eventual production URL is `https://www.shi3ldalliance.co.uk/services`. No production DNS or hosting was changed. On deployment, set `SERVICES_URL` to the origin `https://www.shi3ldalliance.co.uk` (without `/services`); Laravel routes already supply the prefix. Set `SHOP_URL` to the production shop origin, use a valid production TLS certificate, and carry over nginx's `/services` routing.

Included: responsive services homepage; 16 editable service pages covering domestic/commercial/care-home categories and their cleaning options; process, coverage, FAQ, contact and sample gallery pages; privacy/cookie/terms drafts; structured quote form and confirmation; metadata, sharing tags and `/services/sitemap.xml`. The form includes conditional organisation/site details, property type, approximate size, rooms, schedule and contact preferences. File uploads are not collected.

Staff area: **https://shi3ld.rpidevserver/services/staff**. Local login credentials are in `laravel/storage/app/private/services-staff.txt` (mode 600, ignored by Git). This account is separate from Magento admin. There is no public staff registration. Staff can filter/search enquiries, view details, update status/internal notes, export CSV and edit/publish/unpublish the service pages. Exports neutralise spreadsheet formula prefixes.

`php artisan shi3ld:staff EMAIL` creates a staff user using a hidden password prompt; `--bootstrap --no-interaction` generates a private credential file only in the local environment. Existing users' credentials/permissions are preserved. `php artisan db:seed --class=ServicePageSeeder` adds missing sample pages and preserves existing edits. `php artisan shi3ld:deliver-enquiries` runs each minute through the scheduler, tracks acknowledgement/internal delivery separately and retries pending messages. All development mail is captured by Mailpit. Sessions use the separate `shi3ld_services_session` cookie scoped to `/services`; admin responses are private and not cached.

Sample images were generated with the built-in imagegen tool and saved in `laravel/public/services/assets/`: `home-sample.jpg`, `workplace-sample.jpg`, `care-sample.jpg`. Source PNGs are retained alongside them. Prompt set: editorial natural-light photographs of a clean British living room with a cream sofa/oak table/plants; a tidy British workplace with oak meeting table/sage chairs/glass partitions; a welcoming empty care-setting communal lounge with high-backed sage/beige chairs and clear walkways. All prompts exclude people, text, logos and real client claims. The JPG delivery files total about 880 KB; image content was preserved during format optimisation.

Sample content is marked visibly and generated images are illustrative. Contacts/coverage, testimonials, operating claims and final legal wording require approved business content. No analytics or advertising scripts are loaded. Local pages carry `noindex,nofollow`. Booking, payments, workforce scheduling and customer/cleaner portals remain the separate Phase 2 scope in `web-scope.md`.

Implementation checks: Laravel migrations applied; sample content seeded; routes and scheduler registered; Blade views compiled; PHP formatted; nginx configuration accepted; development homepage served HTTP 200. No automated test suite was added or run.

## Combined public site integration — 12 October 2026

| URL | Owner |
| --- | --- |
| `/` | Magento combined shop/services homepage |
| `/shop` | Magento product catalogue entry |
| `/about` | Magento company CMS page |
| `/account` | Redirect to native Magento customer account |
| `/services` and descendants | Laravel service pages, enquiries and staff area |

The Magento homepage links to live catalogue products and Laravel cleaning pages. Both frameworks use matching navigation, footer and sample imagery. Original Magento cleaning CMS pages are inactive; their old URLs redirect to Laravel. `/services/about` redirects to Magento's `/about`. Customer accounts remain Magento accounts; there is no shared login or customer service portal yet.

Edit the homepage/about/shop copy in Magento **Content → Blocks**, under identifiers starting `shi3ld_home_`, `shi3ld_about_copy` and `shi3ld_shop_intro`. The integration data patch creates missing content without replacing existing edits. No sample products or prices were invented; the shop shows a preparation message until products are added.

Run `make integrate` after installing both apps to apply the CMS patch, activate the theme, sync shared assets, generate sitemaps and refresh application caches/nginx. `bin/sync-site-assets` copies Laravel's shared CSS, JavaScript and three sample images into the Magento theme. Run it after shared asset changes, then clear Magento's relevant static assets/cache as appropriate to the deployment mode.

`/sitemap.xml` is an index of native Magento `/sitemap_commerce.xml` and Laravel `/services/sitemap.xml`. Magento cron regenerates the index and commerce sitemap daily; `php bin/magento shi3ld:site:sitemaps` regenerates them manually. Development configuration sets Magento to `NOINDEX,NOFOLLOW`, matching Laravel's local preview. Review indexing, canonical URLs and sitemap discovery before production launch.

`SHOP_URL` is the site origin, without `/shop`; `SERVICES_URL` is also the origin, without `/services`. Calendar, customer service profiles, shared authentication, catalogue import, payments and production deployment remain future milestones.
