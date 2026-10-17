# Shi3ld Alliance

Magento commerce/public website and Laravel cleaning services, based on [web-scope.md](web-scope.md).

Development stack: PHP 8.5 for both apps, Magento Open Source 2.4.9, Laravel 13, Composer 2.10, MySQL 8.4, OpenSearch 3, separate Valkey 9 instances, nginx 1.30 and Mailpit.

## Start development

1. Run `make env` to create local random credentials. `.env` and `.secrets/` are ignored by Git. It also sets `SITE_HOST=shi3ld.<this server's hostname>` (e.g. `shi3ld.gmkserver`, `shi3ld.rpidevserver`); check it, since every URL, the nginx `server_name` and the certificate come from it. Each developer's server keeps its own hostname in its own `.env`, so nothing committed names a server.
2. Install mkcert, trust its local CA with `mkcert -install`, run `./bin/generate-development-cert` (it issues a certificate for `SITE_HOST` and reissues it if `SITE_HOST` changes), then run `make build`. Each server uses the same mkcert CA as CZ on that server.
3. Run `docker compose up -d db laravel-cache mailpit`.
4. Run `./bin/bootstrap-laravel` to install Laravel dependencies, generate its key and migrate its own database. Existing installs keep their existing application key; run the key command only for a new installation.
5. Generate Magento Marketplace access keys at https://commercemarketplace.adobe.com/customer/accessKeys/ . Put them in `.secrets/composer-auth.json`:

   ```json
   {"http-basic":{"repo.magento.com":{"username":"PUBLIC_KEY","password":"PRIVATE_KEY"}}}
   ```

   Keep this file private. It is mounted only into the Magento Composer tooling container.
6. Run `./bin/bootstrap-magento` to download the official release, install Magento, enable project modules and configure search/cache.
7. Run `make up`, then `make workers` once both apps are installed and migrated.

Below, `<SITE_HOST>` is the value in your `.env`. Homepage: https://<SITE_HOST> . Shop: https://<SITE_HOST>/shop . Services: https://<SITE_HOST>/services . Admin: https://<SITE_HOST>/admin/ . HTTPS is published on port 443, with HTTP on port 80 redirecting to HTTPS. To share the server with other sites, set `HTTPS_PORT` (and `HTTP_PORT`) in `.env`, add the port to `SHOP_URL` and `SERVICES_URL` (for example `https://<SITE_HOST>:8448`), recreate nginx (`docker compose up -d nginx`) and run `./bin/configure-development-urls`; the site is then at `https://<SITE_HOST>:8448`. The old localhost ports 9080/9082 also redirect. Mailpit remains local at http://localhost:9085.

On your browsing computer, point both hostnames to this server using your local DNS server or hosts file:

```text
<server IP> <SITE_HOST> services.<SITE_HOST>
```

Windows hosts file: `C:\Windows\System32\drivers\etc\hosts` (edit as Administrator). Linux/macOS: `/etc/hosts`. If CZ's SSL certificate is already trusted, this certificate uses the same CA. Otherwise, import this server's public `$(mkcert -CAROOT)/rootCA.pem` into your computer's trusted certificate authorities. Never copy the CA private key. Certificates and private keys are ignored by Git. The server address is currently assigned by DHCP; reserve it on your router to keep this mapping stable.

`./bin/configure-development-urls` applies Magento's secure storefront/admin URLs and clears the Laravel configuration cache. Laravel publishes secure session cookies. URL values are in root `.env`. To change hostname, edit `SITE_HOST`, run `./bin/generate-development-cert`, recreate nginx (`docker compose up -d nginx`; its config is rendered from `docker/nginx/templates/default.conf.template` at start) and rerun this script.

Magento admin user/password are in local `.env`; don't paste credentials into tickets. Two-factor authentication is temporarily disabled for development at the user's request (`Magento_TwoFactorAuth` and `Magento_AdminAdobeImsTwoFactorAuth`).

`make migrate` runs Magento's `setup:upgrade` and Laravel migrations independently. `make seed` seeds Laravel only. Magento reference data lives in data patches; production records and development demo records must be separated. Never run destructive reset commands against production. Take separate database snapshots before schema changes and coordinate restore points if integrations depend on both databases.

MySQL initialization runs only when its volume is empty. Editing `.env` later does not rotate existing database passwords. Never delete volumes merely to fix a configuration change.

Magento's official bundled nginx configuration is installed by the bootstrap script; the initial shop placeholder returns 503 until installation completes. Laravel runs independently while Magento keys are pending. Workers are optional until application installs complete.

Architecture, milestones and remaining client inputs: [development plan](docs/development-plan.md).

Official compatibility references: [Magento requirements](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements), [Magento release](https://experienceleague.adobe.com/en/docs/commerce-operations/release/notes/magento-open-source/2-4-9), [Laravel releases](https://laravel.com/docs/13.x/releases).

## Current milestone

Laravel 13.35.0 is installed with PHP 8.5.11 and Composer 2.10.3. Its MySQL migrations have run. The services quote form is available at `/services/quote`, with conditional fields, enquiry persistence, unique references, CSRF protection, rate limiting and persistent customer/internal notification tracking. Laravel queue and scheduler containers run independently of Magento.

Magento Open Source 2.4.9 is installed. Shi3ld_Catalog, Shi3ld_Content and Shi3ld_Integration are enabled, their data/schema patches have run, and the Shi3ld Alliance theme is activated. All 14 indexers were rebuilt and Magento reports its schema up to date. The outbox schema is a foundation; event capture, dispatcher retries, webhook authentication, deduplication and reconciliation remain future work. The Magento API client is prepared; authenticated API integration remains a later milestone.

Before public launch, approve the privacy policy/retention rules, company contacts, branding, service coverage, quotation process and staff access. Customer/internal notification delivery is retried independently by the scheduler, up to five attempts, with 1, 5, 15 and 60 minute backoff. Failed delivery is flagged in the staff inbox and enquiry details. Delivery is at least once: a process crash after SMTP accepts a message but before the timestamp is saved can cause a repeat acknowledgement. Outbound emails currently go to Mailpit.

To run only the Laravel services:

```bash
docker compose up -d db laravel-cache mailpit
docker compose up -d --no-deps php-laravel nginx
docker compose --profile workers up -d laravel-queue laravel-scheduler
```

The combined homepage and shop are served by Magento; domestic cleaning is available at `/services/domestic-cleaning` in Laravel. `make up` starts the full stack; `make workers` starts Magento cron and Laravel queue/scheduler services.

Runtime PHP containers mount only their own application directories. The nginx container mounts Magento and Laravel public files without root credentials. Only temporary installation tooling mounts the repository root. This keeps Laravel runtime code from reading Magento write credentials in root `.env`.

## Magento installation details

Magento admin: https://<SITE_HOST>/admin/ . Admin credentials remain in the local root `.env`, and two-factor authentication is temporarily disabled for development. Mailpit captures development messages at http://localhost:9085 . Native SMTP settings are stored in Magento's ignored `app/etc/env.php` using `bin/configure-magento-development`.

MySQL runs with `log_bin_trust_function_creators=1`, which permits Magento's indexing triggers without granting its database account global SUPER privileges. The installer checks the completed installation date so an interrupted install can resume without deleting its database.

Adobe's [VULN-39341 hotfix](https://experienceleague.adobe.com/en/docs/commerce-knowledge-base/kb/announcements/commerce-apsb26-146) is applied from `magento/patches/VULN-39341_249.patch`. Composer hooks run `magento/bin/shi3ld-apply-patches.php` after install/update; it checks for an already applied patch and stops if source files do not match. Review this version-specific patch when upgrading Magento.

Smoke checks completed: shop, domestic cleaning page and Laravel quote page return HTTP 200; Magento schema is up to date; all three Shi3ld modules are enabled; theme ID 4 is active. This is the development foundation. Approved launch catalogue content, payment/shipping settings, final page design and launch acceptance remain in the delivery plan. Catalogue transfer tooling is described below.

## Laravel services preview — 12 October 2026

The public services site now runs at **https://<SITE_HOST>/services**, using the same hostname as Magento. Nginx sends `/services` and its descendants to Laravel and serves `/services/assets/` from Laravel public files. Magento continues to serve the shop and `/admin/`. The previous services subdomain redirects to this path.

The eventual production URL is `https://www.shi3ldalliance.co.uk/services`. No production DNS or hosting was changed. On deployment, set `SERVICES_URL` to the origin `https://www.shi3ldalliance.co.uk` (without `/services`); Laravel routes already supply the prefix. Set `SHOP_URL` to the production shop origin, use a valid production TLS certificate, and carry over nginx's `/services` routing.

Included: responsive services homepage; 16 editable service pages covering domestic/commercial/care-home categories and their cleaning options; process, coverage, FAQ, contact and sample gallery pages; privacy/cookie/terms drafts; structured quote form and confirmation; metadata, sharing tags and `/services/sitemap.xml`. The form includes conditional organisation/site details, property type, approximate size, rooms, schedule and contact preferences. File uploads are not collected.

Staff area: **https://<SITE_HOST>/services/staff**. Local login credentials are in `laravel/storage/app/private/services-staff.txt` (mode 600, ignored by Git). This account is separate from Magento admin. There is no public staff registration. Staff can filter/search enquiries, view details, update status/internal notes, export CSV and edit/publish/unpublish the service pages. Exports neutralise spreadsheet formula prefixes.

`php artisan shi3ld:staff EMAIL` creates a staff user using a hidden password prompt; `--bootstrap --no-interaction` generates a private credential file only in the local environment. Existing users' credentials/permissions are preserved. `php artisan db:seed --class=ServicePageSeeder` adds missing sample pages and preserves existing edits. `php artisan shi3ld:deliver-enquiries` runs each minute through the scheduler, tracks acknowledgement/internal delivery separately, retries due messages with bounded backoff, and flags exhausted delivery for staff. All development mail is captured by Mailpit. Sessions use the separate `shi3ld_services_session` cookie scoped to `/services`; admin responses are private and not cached.

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

Edit the homepage, About page and shop introduction in Magento **Content → Shi3ld Website Pages** using **Edit with Page Builder**. The original introduction blocks are retained as legacy content backups; the visual pages now own their text and layouts. The conversion copies existing block edits and skips pages with independently modified content. No sample products or prices were invented; the shop shows a preparation message until products are added.

Run `make integrate` after installing both apps to apply the CMS patch, activate the theme, sync shared assets, generate sitemaps and refresh application caches/nginx. `bin/sync-site-assets` copies Laravel's shared CSS, JavaScript and three sample images into the Magento theme. Run it after shared asset changes, then clear Magento's relevant static assets/cache as appropriate to the deployment mode.

`/sitemap.xml` is an index of native Magento `/sitemap_commerce.xml` and Laravel `/services/sitemap.xml`. Magento cron regenerates the index and commerce sitemap daily; `php bin/magento shi3ld:site:sitemaps` regenerates them manually. Development configuration sets Magento to `NOINDEX,NOFOLLOW`, matching Laravel's local preview. Review indexing, canonical URLs and sitemap discovery before production launch.

`SHOP_URL` is the site origin, without `/shop`; `SERVICES_URL` is also the origin, without `/services`. Calendar, customer service profiles, shared authentication, payments and production deployment remain future milestones. Catalogue promotion now has its own Magento Admin workflow below.

## Review fixes — 14 October 2026

- Laravel bootstrap uses `composer install` and preserves the lockfile and committed Boost instructions. Dependency upgrades are a deliberate, separate change.
- Magento tracks only Composer manifests, project modules/theme, `app/etc/config.php` and the hotfix files. Composer deploys the remaining skeleton. Generated runtime files, including `pub/opt/`, are ignored.
- The unused Magento reporting connection/user is removed. On an existing database volume, run `./bin/retire-magento-reporting`; new volumes create only the application database users. The API client remains unused scaffolding; any future reporting feature must define explicit access permissions.
- OpenSearch and Valkey use image digests matching the development images inspected on 14 October. Update those digests deliberately on both servers.
- Laravel owns the shared CSS/JS/images. Run `bin/sync-site-assets` after an asset change. CI runs `bin/sync-site-assets --check` and rejects drift.
- Notifications stop after five delivery attempts per recipient channel and back off for 1, 5, 15 and 60 minutes. Failed delivery is shown to staff. An interrupted final attempt is flagged for review; SMTP delivery still has at-least-once semantics.

### Team workflow

Each developer sets their own identity locally; no identities or credentials are committed:

```bash
git config --local user.name "Your Name"
git config --local user.email "your-address@example.com"
git switch dev
git pull --ff-only origin dev
git switch -c feature/short-description
```

Commit and push the feature branch, then open a pull request into `dev`. Release approved changes from `dev` into `main`. Enable required checks/reviews in GitHub branch protection when the team agrees on its release policy; the workflow file does not enforce repository settings by itself.

The GitHub Actions workflow runs Laravel tests, Pint, repository tracking checks, shell syntax checks and shared asset comparison for pull requests into `dev`/`main`. It uses the documented [checkout action](https://github.com/actions/checkout) and [PHP setup action](https://github.com/shivammathur/setup-php).

Local commands:

```bash
docker compose exec -T php-laravel php artisan test --compact
docker compose exec -T php-laravel vendor/bin/pint --format agent
bin/sync-site-assets --check
bin/check-repository
```

Tests map the `mysql` connection name used by the application to SQLite in memory and use array cache/session/mail. They never migrate or reset the development MySQL databases. Coverage includes quote validation/honeypot/rate limiting, staff permissions/login, status changes, CSV safety, content publishing, literal search and notification retries.

### Updating an existing checkout after the repository cleanup

Git removes the formerly tracked skeleton when this cleanup is merged. Stop the app/worker containers first, pull the approved branch, then restore core files through the locked dependencies:

```bash
docker compose --profile workers stop nginx php-magento magento-cron php-laravel laravel-queue laravel-scheduler
git pull --ff-only origin dev
./bin/bootstrap-laravel
./bin/bootstrap-magento
./bin/retire-magento-reporting
make up
make integrate
make workers
```

Keep database volumes, local `.env`, Magento `app/etc/env.php` and uploaded media. Back up both databases before applying schema changes. No existing product/category/enquiry records are removed by these review migrations.

Before production: choose and configure a CAPTCHA provider for the public quote form, approve its privacy wording, enable Magento 2FA and production mode, turn off debug logging, configure production edge security/cache headers and replace mkcert TLS. Acknowledgements already exclude submitted free text; the regression test preserves that restriction. The development settings remain active while this project is being previewed.

## Development sample catalogue

Run `docker compose exec -T php-magento php bin/magento shi3ld:catalog:seed-samples` explicitly in developer mode to add 12 sample configurable products across four sample categories, each with three sizes and two colours (72 hidden simple variants). Product definitions are in `magento/app/code/Shi3ld/Catalog/etc/sample-products.json`. Existing SKUs and category edits are preserved; rerunning does not reset stock or pricing. Each sample uses the `SHI3LD-SAMPLE-` SKU prefix and has illustrative prices, size/colour options and stock. It is separate from the approved launch catalogue.

After seeding, reindex Magento, run `shi3ld:site:sitemaps` and clean `config eav layout block_html full_page` caches. `/shop` shows category links and configurable product cards. Sizes/colours are selected on each product page. Native Magento placeholder images are used until sample or approved product imagery is supplied.

## Visual CMS editing

Magento Page Builder is enabled for native CMS pages. Open **Content → Shi3ld Website Pages**, select a page, expand **Content** and click **Edit with Page Builder**. The homepage contains six independent rows; drag them to reorder sections. Text, images and buttons are native editable elements, and columns can be resized. Images are in the Media Gallery under `wysiwyg/shi3ld` and can be replaced or uploaded through the image editor. You can also use **Content → Pages → Add New Page** for additional CMS pages.

The homepage product section is Magento's live Products widget, styled by the theme. Its product selection/count can be changed in Page Builder. The shop's live catalogue is a reusable block tile; move it within the page, but keep the tile to retain automatic catalogue/category rendering. Product records are edited under **Catalog → Products** and categories under **Catalog → Categories**. Header/footer content is managed in shared CMS blocks through the frontend editor. Laravel service pages have a matching editor in their staff area.

Use **Close Full Screen** (the exit icon at the top of the canvas), then **Save** to publish directly from native Page Builder. Its mobile viewport helps inspect responsive content. CMS saves invalidate Magento's page cache. SEO titles and descriptions remain available in each page's **Search Engine Optimization** section. The Shi3ld frontend editor below adds separate drafts, revision history and scheduled publication.

The `EnableVisualPages` Magento data patch performs the conversion once and preserves existing page titles, URLs and metadata. It only converts the original Shi3ld template directives; other page content is left intact. Pre-conversion page JSON is kept in ignored `magento/var/shi3ld/cms-backups/`, with a private database snapshot in `backups/` on this server. Re-running upgrades does not reset visual edits.

### Frontend visual editor

In **Content → Shi3ld Website Pages**, choose **Open frontend visual editor**, **Edit website header** or **Edit website footer**. After visiting Magento Admin, CMS editors also see editing shortcuts on the frontend. The toolbar is added only after Magento confirms the current Admin session and the **Visual editor: drafts and revisions** permission. **Visual editor: publish content** controls publication separately. Native Page Builder remains available for advanced widget settings.

The editor shows the actual website layout in an isolated canvas. Click headings, text, images or links to edit them. The left panel provides text formatting, link destinations, image descriptions, colours, alignment and spacing. Images can be selected from the gallery or uploaded as JPEG, PNG, GIF or WebP (5 MB maximum). The brand mark can be replaced with an image. Drag section cards or use their arrow buttons to reorder; duplicate/remove sections or add approved text, image/text, two-column, call-to-action and FAQ layouts. Desktop, tablet and mobile buttons change the preview width. Catalogue widgets and service forms are rendered dynamically and are protected from inline record edits.

**Save draft** (or Ctrl/Command S) keeps public content unchanged. **Preview draft** opens a private 15-minute preview in the same browser. Magento previews require an active Admin session and end on logout, expiry, account disablement or password change; preview responses bypass page caching and are marked noindex. **Publish** applies the saved content and refreshes page caches. **History** shows up to 100 recent draft/publication snapshots; viewing a revision is read-only, and **Restore as draft** creates a new working version. Concurrent draft saves and changes made through native CMS editing are checked before publishing. Unsaved changes can be recovered from the same browser tab after a reload; this recovery is separate from saved database drafts.

For services, sign in at **/services/staff/login**, open **Service page content**, then choose **Open visual editor**. This covers the services homepage, individual service pages and information pages. It uses Laravel staff permissions, CSRF protection, its own draft/revision tables and private signed previews tied to the staff session. Existing structured fields remain the source until a visual version is first published; subsequent content changes use the visual editor. While the website is offline, its existing Magento administrator preview requirement also applies to the services editor.

Magento owns `shi3ld_cms_document` and `shi3ld_cms_revision` through `db_schema.xml`; Laravel owns `site_content_documents` and `site_content_revisions` through its migration. Apply Magento `setup:upgrade` and Laravel `artisan migrate` when deploying this code. The `CreateEditableRegions` patch creates shared header/footer blocks once, preserving existing blocks. Their published content is shared with Laravel through an atomic `magento/var/shi3ld/site-access/design.json` file on the existing read-only services mount. Run `bin/magento shi3ld:cms:sync-design` after an initial deployment; future shared-block saves refresh it automatically. Drafts are never exported to that file. Magento preview records live in private ignored `var/shi3ld/cms-previews/`; Laravel uploads live in ignored `public/services/assets/content/`. Include databases and uploaded media in backups. A private pre-install database snapshot is retained in `backups/before-visual-editor-2026-10-21.sql` on this development server.

### Advanced frontend builder — 23 October 2026

Open **Content → Builder Design Library** in Magento, or **Service page content → Design library** in Laravel. Existing visual editors now have a **Design** tab, a nested element navigator and a **Library** button. Designs start as drafts; installing this update does not replace the current homepage. This is a custom Shi3ld editor and does not load Elementor or WordPress plugins.

- **Design and layout:** desktop/tablet/mobile styles, hover/focus styles, grid and flex containers, nested containers, width dragging, element names/classes, locks, multiple selection with Shift, style copy/paste/reset, duplication/reordering and up to 60 undo steps. Global brand designs control shared colours, fonts, content width, spacing and button appearance across Magento and Laravel. Page CSS is scoped to its page, element CSS to its element, and global CSS to the public site. Use `selector` for the current scope. The CSS editor includes highlighting and Ctrl/Command Space property completion; `@media` and `@supports` are supported. Imports, URL functions, scripts, font-face declarations and keyframes are excluded. Use the media picker for backgrounds.
- **Reusable designs:** save sections or page copies, insert independent copies, or insert published sections/forms as linked blocks. Editing and publishing the original linked section updates its instances. Applied page templates need exactly one **Page content slot** so the existing content is retained. Choose **Library only** for a saved page copy. Header/footer templates must explicitly target their region. Display rules accept comma-separated paths, wildcard paths, exclusions, the local app's audience and UTC start/end times; higher template priority wins. Magento product arrangements support gallery left/right/above and styling native gallery/information/details/related slots; native pricing, variants and basket controls remain responsible for purchasing.
- **Content and media:** article card designs bind title, excerpt, image, URL, author, date and category fields; select a published card design in the blog widget's **Card template** setting. Dynamic bindings use the current page/article/service/product context. Tabs, image galleries/lightboxes, click-to-load YouTube/Vimeo videos, icon lists and navigation sections are available under **Add**. Image fit, aspect ratio, focal point, gradients and background selection use element settings; these do not crop the uploaded original. Entrance effects respect reduced-motion preferences.
- **Forms and announcements:** create a form design, configure service type and additional fields, publish it, then insert it as a linked block. Forms reuse the existing Laravel enquiry inbox and notification delivery workflow, with required contact/privacy fields, validation, CSRF, a honeypot and rate limits. Editor previews disable submission. Popup designs support delay, scroll, link/button and announcement-banner triggers, with session/day/visit dismissal. For a click trigger, use a link to `#popup-<design-key>`; the key is shown in the design's linked markup/package. Set paths and dates to limit publication.
- **Publishing and roles:** request review, compare the published page and working draft side by side, schedule a saved draft or cancel scheduling. The Library contains the pending review/publication queue. Schedules use UTC and run through the existing minute schedulers; editing the draft afterwards prevents the older scheduled version from being published. Magento's **Visual editor: layouts, CSS and design library** permission controls design tools, while existing edit/publish permissions remain separate. Existing Laravel staff keep design/publication rights; restrict them with `php artisan shi3ld:builder:process --user=staff@example.com --designer=0 --publisher=0`. Content editors can change text, links and image descriptions/sources while layout, styles and locked content are checked on the server.
- **Portability and guidance:** export/import JSON design packages with linked designs and supported original raster images (5 MB/image, 20 MB total media, 30 MB package). Imports create draft library items; publish imported linked designs before publishing pages that use them. Global brand and product designs are owned by Magento and cannot be imported into Laravel's library. Magento-specific dynamic widgets use a shared read-only renderer when displayed in Laravel. The page review tool flags basic image-description, heading, label, contrast, SEO-description and page-size issues; it is guidance rather than an accessibility certification.

Magento owns `shi3ld_builder_asset`, `shi3ld_builder_page` and `shi3ld_builder_workflow` through declarative schema. Laravel's `2026_10_23_115645_create_site_builder_assets_and_workflows` migration owns its asset/workflow tables and staff permission columns. Only published Magento assets and shared-region settings are exported to `var/shi3ld/site-access/builder.json` on Laravel's existing read-only mount. Laravel writes remain in its own database. Shared branding/header/footer changes flow from Magento to Laravel; Laravel-only designs remain local to services. Magento/Laravel sessions remain separate. Publishing audience-dependent Magento designs disables full-page caching until those rules are removed; timed rules invalidate it once per minute.

Deployment after pulling these changes:

```bash
./bin/sync-builder-runtime
./bin/sync-site-assets
docker compose exec -T php-magento php bin/magento setup:upgrade --keep-generated
docker compose exec -T php-laravel php artisan migrate --force --no-interaction
docker compose exec -T php-magento php bin/magento setup:di:compile
docker compose exec -T php-magento php bin/magento shi3ld:cms:sync-design
docker compose exec -T php-magento php bin/magento cache:clean config layout block_html full_page reflection
docker compose exec -T php-laravel php artisan view:cache
docker compose --profile workers up -d
```

When upgrading an already compiled checkout after constructor changes, clear the old generated dependency metadata using the deployment's normal generated-code refresh before running Magento CLI commands. `bin/sync-builder-runtime` distributes the same CSS/schema/render engine to Laravel; its `--check` option detects drift. The development database backup before this upgrade is `backups/before-advanced-builder-2026-10-23.sql` (private and ignored). PHP/JavaScript syntax, Magento dependency compilation and Blade compilation are checked during installation; browser interaction and form submission acceptance remain to be reviewed.

## Blog articles and reusable article widgets

Magento owns `/blog`, `/blog/category/<category-key>` and `/blog/<article-key>`. There are no duplicate Laravel article routes. In Admin, open **Content → Blog Articles** to create an unpublished article and open its frontend visual editor. The **Page** tab contains the title, SEO title/description, URL key, excerpt, author, featured flag, category assignments and featured image/alternative text. Article bodies use the existing section/image/button editor. Save drafts, open a private preview, publish, inspect revision history or restore a revision as a draft. **Unpublish** on the article list hides the article from visitors, widgets and sitemaps. Editing uses the existing `visual_edit` role permission; publication, unpublication and category management require `visual_publish`.

Manage editorial categories in **Content → Blog Categories**. They are separate from shop categories; each article can belong to multiple categories. Categories support parent categories, descriptions and SEO fields, and can be disabled without losing assignments. The advanced management screen below adds author profiles, tags and translations for configured store views.

New article URL keys are generated with Magento's `TranslitUrl` filter, checked against existing article keys and native URL rewrites, and given a numeric suffix when an automatically generated key is taken. Title changes preserve an existing key. Editors can change a key deliberately; publication writes native Magento rewrites with permanent redirects from historical URLs to the latest URL. Category URL changes work similarly and do not change article URLs. Direct controller URLs redirect to the canonical public address. Published articles/category listings are added to the native commerce sitemap, with blog metadata, canonical links and article structured data. Draft previews and search results are marked noindex; ordinary blog pages inherit the store's robots setting. Publication/category changes refresh page caches and regenerate the sitemap; the existing sitemap cron retries generation if it fails.

The **Blog Articles** section in the frontend editor (**Add → Blog articles**) supports category multi-select, article count (1–24), desktop/tablet/mobile columns, newest/oldest/featured/manual ordering, image above/below/beside text, text-only cards, list and featured-grid designs, image shapes, spacing, backgrounds and Read more button styles. Date/author/category/excerpt visibility, excerpt length, heading and View all are configurable. Multiple categories match **any** selected category, without repeating articles. Only published articles appear; an empty widget stays hidden. List layout uses one column. Featured grids give the first card extra space. Mobile/tablet layouts adapt automatically to the configured columns. Images use responsive thumbnails and a branded fallback.

**Content → Blog Widget Builder** provides the same settings, responsive preview widths and a generated Magento widget directive to copy into a Page Builder HTML Code element. The native **Insert Widget → Shi3ld Blog Articles** interface is also available. Manually selected articles use IDs shown in the article list and widget builder. Category settings store IDs so renaming a category does not break saved widgets. The shortcode is generated automatically; there is no WordPress dependency.

`Shi3ld_Blog` owns its article, taxonomy and editorial tables through `etc/db_schema.xml`, with its generated schema whitelist included in source. It reuses `Shi3ld_Content` draft/revision tables through the editor provider interface. On an existing install, run:

```bash
docker compose exec -T php-magento php bin/magento module:enable Shi3ld_Blog
docker compose exec -T php-magento php bin/magento setup:upgrade --keep-generated
./bin/sync-site-assets
docker compose exec -T php-magento php bin/magento shi3ld:site:sitemaps
docker compose exec -T php-magento php bin/magento cache:clean config layout block_html full_page reflection
```

The one-time `AddHomepageArticles` data patch appends the default article section to the existing homepage and records its CMS revisions. It preserves existing content. If there is an existing homepage draft, it leaves that draft intact; add the section through the visual editor instead. No sample articles are added by migrations. The optional `bin/magento shi3ld:blog:seed-samples` command creates three labelled sample drafts on local developer-mode sites only; add `--publish` to publish newly created samples. Existing samples are preserved. This development server has three published samples across Cleaning guides, Product guides and Planning ahead, using existing illustrative images. Replace or unpublish them before launch. Database backup: `backups/before-blog-2026-10-21.sql` on this server. Original uploaded images and the database belong in backups; `pub/media/shi3ld/blog-cache/` contains regenerable thumbnails.

### Advanced blog management — 24 October 2026

Open **Magento Admin → Content → Blog Management**. Article visual editors now have a **Blog** tab alongside the shared layout tools. Public articles remain under `/blog`; Laravel owns service enquiries and does not migrate or write Magento's tables.

- **SEO and sharing:** search and social previews, duplicate metadata checks, missing image/alternative text checks, heading checks, review reminders, and checks for up to 50 internal links per article. Service links are checked against the local services sitemap. External URLs are not crawled. Social title, description and image can override article defaults; canonical overrides are available. Checks are editorial guidance and do not predict rankings.
- **Authors and discovery:** author biographies, expertise, images and public archives; nested categories; topic tags and archives; keyword relevance, category, tag, author and publication-date filters. Filtered search pages are noindex. Author and article structured data, canonical URLs, native rewrite redirects, sitemap entries and published store-view hreflang links are generated by Magento.
- **Article experience:** generated heading anchors, an optional table of contents, estimated reading time, optional reading progress, share/copy buttons, image captions/credits, related articles with ordered pins/exclusions, selected Magento products with native pricing, and links to Laravel service pages. How-to, buying guide, comparison and case-study starters populate a draft for editing.
- **Editorial workflow:** writer assignment, deadlines, review dates and retirement dates; a monthly calendar with drag or keyboard-accessible date changes; the existing review/scheduled-publication workflow; bulk assignments, topics, publication and unpublication. Bulk actions report each result and preserve version checks. Dates use UTC. Scheduled publication uses the existing one-minute content cron; retirement and blog maintenance run every five minutes.
- **Collaboration:** server draft autosave after idle periods, the existing revision history, internal notes, and 90-second editing leases refreshed every 30 seconds. A second administrator sees the current editor; publishers can explicitly take over. Concurrent version/publication checks still apply. There is no simultaneous character-by-character co-editing.
- **Readers:** helpfulness votes, moderated plain-text comments, a honeypot and request limits; confirmed email subscriptions with category preferences, owner-only preference management and unsubscription, daily/weekly digests, and RSS at `/blog/rss/index` (optional `?category=<id>`). Confirmation and unsubscribe links open a review form; the action requires a POST. Digest emails contain up to 24 recent matching articles and recheck publication/preferences before sending.
- **Analytics and enquiries:** aggregate article reads, product/service clicks, helpful votes and completed Laravel enquiry attribution. Anonymous counts are approximate, deduplicated by a keyed IP hash and day; raw IP addresses are not retained by this module. DNT/GPC and private previews skip tracking. Signed article references can follow service links or embedded service forms into the existing enquiry inbox. Conversion delivery is queued with five attempts and native deduplication; staff enquiries show the referring article after acknowledgement. Existing web-server logging is unchanged.
- **Maintenance and portability:** review/retirement reminders, bulk operations, JSON article/media packages and CSV summaries. Export up to 50 selected articles, within a 20 MB package. Imports create article/translation/linked-design drafts and reuse existing taxonomy by URL key. Article URL keys are retained when available; collisions and omitted related-article references are reported. Imports clear canonical overrides, writer assignments and retirement dates. Images follow the builder's raster validation and size limits. Reader details, analytics and private notes are excluded. Keep full database/media backups for recovery.
- **Languages:** create an article or category translation for an existing secondary Magento store view. Article translations use the visual editor; category translations use the Languages form with draft/revision checks. Publish each translation explicitly. The default store view uses the original article/category; untranslated secondary views fall back to original content with its default-view canonical URL. Packages match translations by configured store code and report missing destinations. This does not configure new store views or automatically translate copy.

**Comments, subscriptions and email delivery start disabled.** A publisher can enable them in **Blog Management → Subscriptions**, set daily/weekly frequency and choose 30–730 days of analytics/rejected-feedback retention (default 180). Individual articles must also allow comments. Delivery uses Magento's transactional mail configuration; development mail is captured by Mailpit. The one-minute mail worker retries failures and exposes a manual retry action. No subscribers or messages are created by installation. Delivery has at-least-once semantics if SMTP succeeds immediately before a database failure.

Editors need `visual_edit`; publication, taxonomy, moderation, analytics and reader settings need `visual_publish`. Package import also needs the builder `design` permission. Existing private preview and website offline access controls continue to apply.

Deploy after backing up both databases and uploaded media:

```bash
docker compose exec -T php-magento php bin/magento setup:upgrade --keep-generated
docker compose exec -T php-laravel php artisan migrate --force --no-interaction
docker compose exec -T php-magento php bin/magento setup:di:compile
docker compose exec -T php-magento php bin/magento shi3ld:blog:prepare
docker compose exec -T php-magento php bin/magento shi3ld:site:sitemaps
docker compose exec -T php-magento php bin/magento shi3ld:cms:sync-design
docker compose exec -T nginx /docker-entrypoint.d/20-envsubst-on-templates.sh
docker compose exec -T nginx nginx -t
docker compose exec -T nginx nginx -s reload
docker compose exec -T php-laravel php artisan view:cache --no-interaction
docker compose restart laravel-queue laravel-scheduler
docker compose exec -T php-magento php bin/magento cache:clean config layout block_html full_page reflection
```

`shi3ld:blog:prepare` creates the private `magento/var/shi3ld/site-access/blog.key`; Laravel reads it through the existing read-only shared mount. Preserve it securely with the environment backup; do not commit it. Restoring subscriber/delivery data also requires Magento's encryption key; encrypted enquiry references require Laravel's application key. The Laravel migration adds attribution fields only to Laravel's `service_enquiries`. The pre-upgrade local database snapshot is `backups/before-advanced-blog-2026-10-24.sql` (private and ignored). Installation includes syntax, dependency, schema and template compilation checks; browser interaction, enquiry submission and email-delivery acceptance still require review.

## Website online / offline

In Magento Admin, open **Content → Website Online / Offline**, choose a status and save. The switch covers both Magento pages and Laravel `/services`, including service staff pages. Visitors receive a branded **503 Service Unavailable** page with `Retry-After`; you can edit its plain-text message. Magento Admin and static/media assets stay available. This is an access switch for planned content work; Magento's native command-line maintenance mode remains separate.

While offline, click **Open private preview** in that screen. Sign in with an existing Magento administrator account; the normal Magento login and any enabled second factor still apply. The offline page's sign-in link leads to Admin, where an offline notice links to the preview screen. The browser receives a secure, HttpOnly, host-only preview cookie for one hour. Preview access covers both apps and ends on **End my private preview**, Magento Admin logout, account disablement, password change or expiry. Bringing the site online and taking it offline again invalidates old previews. Switching site status requires the **Website Online / Offline** role permission; authenticated administrators can preview without that permission. Customer and Laravel staff logins do not grant offline preview access.

Alternatively, select **Redirect to frontend — signed-in administrators** in the **Website status** dropdown and save. The site stays offline for visitors, while an existing Magento Admin login in the same browser grants frontend access automatically, including `/services`. The saving administrator receives access immediately. Other signed-in administrators are recognised through a same-origin browser check under the Admin cookie path; the requested frontend URL is preserved. JavaScript is required for that automatic check. There is no separate preview button in this mode. Access is tied to Magento's active Admin session record and ends on expiry, revocation or logout; any enabled second factor remains required. Switching between manual and automatic access invalidates previews from the previous mode. Anonymous page requests keep a 503 response, and API/Ajax requests remain offline until administrator access has been established through a page visit.

The nginx access check runs before application page caches; preview HTML responses are private and not stored by browser/shared caches. With the site online, public requests do not need the preview authentication service. State is stored outside the document root in ignored `magento/var/shi3ld/site-access/offline.json`, with hashed preview records in its private `sessions/` directory. `bin/init-env` creates the shared directory before containers start. Preserve the state directory across deploys if the site should remain offline; remove `offline.json` on the host as an emergency recovery to online. Internal Docker Magento API traffic remains private and authenticated as before. The nginx admin exemption matches this project's configured `/admin` path; update it if the backend front name changes.

## Catalogue promotion between environments

Magento Admin **Catalog → Catalogue Transfer** provides an approved download/upload workflow for **development → staging → production**. Set `SHI3LD_ENVIRONMENT=development`, `staging` or `production` in each server's private root `.env`, then recreate `php-magento` and `magento-cron` so their environment matches. When unset, the tool treats Magento production mode as production and developer mode as development. Deploy the same Shi3ld module code and normal Magento schema/data patches to the destination before moving catalogue data. No remote server credentials are stored, and no scheduled or automatic network transfer is configured.

1. On development, download a catalogue ZIP. Enter approved parent SKUs or leave the selection empty for the default website catalogue. All configurable variants are included. Sample SKUs are excluded by default. Price and stock data are opt-in at export.
2. On staging, upload the ZIP and preview the SKU list, create/update counts, category and image counts, new attribute definitions and validation errors. Choose whether to apply packaged prices and stock. Previewing writes only a private transfer record/package; it does not modify catalogue records.
3. Apply the reviewed package. Existing prices, tax classes and inventory are preserved unless opted in. New products are disabled unless both price and stock import are selected. Missing inventory sources and product tax classes/rules must be configured on the destination first. Existing product status, visibility and website assignments are preserved. Products match by SKU, basic custom attributes by code, options by label, attribute sets by name, and categories by their URL-key ancestry under the destination's default root. Root-level source categories are imported as normal categories under that root.
4. Review staging, export the approved staging catalogue, then upload/preview/apply on production. Production requires typing its hostname. Direct development → production promotion is rejected, and `SHI3LD-SAMPLE-` SKUs are always rejected on production.

Packages contain versioned `catalogue.json` plus image files with SHA-256 integrity checks. Supported catalogue data: simple/configurable products, global/admin product names and descriptions, SEO fields, weight/country, lead time and basic custom attributes/options, category content, configurable SKU relationships, and JPEG/PNG/GIF gallery files/roles. Populated custom attributes with unsupported backends/input types are listed as exclusions in the preview. Images are matched by content to avoid adding a copy on every import. Imported attributes use Magento EAV setup APIs; product/category/media changes use Magento services; explicit stock changes use Inventory APIs. Laravel does not own or migrate these records.

Each apply saves a private snapshot of existing selected products, records every saved SKU and refreshes catalogue indexes, storefront caches and the commerce sitemap. Imports run in steps and do not automatically roll back a partial failure; completed SKUs and errors remain visible in the import record. A catalogue snapshot supports restoring existing catalogue data; keep full database/media backups for complete recovery, including newly created records or attribute changes. Transfer records, snapshots and bundles are under ignored `magento/var/shi3ld/catalogue-transfer/` with private permissions; retain/archive them according to the deployment backup policy. Previews expire after 24 hours, and an already applied transfer cannot be submitted again.

The initial scope is one default website with admin/global product values and at most 1,000 products including variants per package. Existing categories, product memberships, gallery entries and products are retained when absent from the incoming package. Custom product options, videos, swatch definitions, tier/group prices, complex extension attributes and store-view overrides require separate handling. This workflow promotes approved catalogue content; it never copies orders, customers, reservations, admin users, credentials or payment/shipping configuration. Admin upload size follows PHP's limit (currently 20 MB); the CLI accepts packages up to 256 MB expanded.

Equivalent Magento CLI commands (paths are inside `php-magento`):

```bash
# Source: add --include-prices / --include-stock only when required.
docker compose exec -T php-magento php bin/magento shi3ld:catalog:export --sku=APPROVED-PARENT-SKU

# Copy the generated ZIP to the next environment, then preview there.
docker compose exec -T php-magento php bin/magento shi3ld:catalog:import var/shi3ld/catalogue-transfer/catalogue-FILENAME.zip --dry-run

# Apply content while preserving existing prices and stock.
docker compose exec -T php-magento php bin/magento shi3ld:catalog:import var/shi3ld/catalogue-transfer/catalogue-FILENAME.zip --apply

# Explicit price/stock promotion; production also needs its actual hostname.
docker compose exec -T php-magento php bin/magento shi3ld:catalog:import var/shi3ld/catalogue-transfer/catalogue-FILENAME.zip --apply --prices --stock --confirm-host=www.shi3ldalliance.co.uk
```

CLI preview is the default without `--apply`. `--allow-same-environment` is available for a deliberate local refresh/recovery and does not permit skipping staging; production still needs hostname confirmation and rejects sample products. Catalogue Transfer has a dedicated Admin role permission because it can create attributes and categories as well as save products. Existing Magento CSV import/export remains available separately.
