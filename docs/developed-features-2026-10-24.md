# Shi3ld: developed features and operating guide

**Report date:** 24 October 2026

**Project:** `~/shi3ld`

**Development website:** <https://shi3ld.rpidevserver>

**Scope:** the seven requested website, editing, blog and catalogue requirements.

This report describes the implementation present in the local project, how administrators use it, and its current limits. It records development delivery; it does not confirm a production release or a completed transfer to another server. The latest builder/blog work has passed technical compilation and syntax checks, while browser and operational acceptance remain to be reviewed.

## Delivery overview

| No. | Requested feature | Development status | Main access point |
| --- | --- | --- | --- |
| 1 | Website status dropdown: redirect to frontend | Done: automatic access for signed-in Magento administrators | Content → Website Online / Offline |
| 2 | Investigate frontend page builders | Investigated and implemented: native Magento Page Builder plus a custom frontend visual editor | Content → Shi3ld Website Pages / Builder Design Library |
| 3 | Blog articles with categories, featured image and Read more | Implemented, with additional editorial and reader features | Content → Blog Articles / Blog Categories / Blog Management |
| 4 | Configurable article shortcode/widget, responsive layouts and automatic SEO URL keys | Implemented through a native Magento widget and visual controls | Content → Blog Widget Builder |
| 5 | Modify Magento pages through a WordPress-style CMS experience | Implemented for CMS pages, shared content blocks and articles | Content → Shi3ld Website Pages |
| 6 | Take the site offline while allowing administrator viewing | Implemented across Magento and Laravel services | Content → Website Online / Offline |
| 7 | Synchronise products between environments | Implemented as reviewed catalogue export/import: development → staging → production | Catalog → Catalogue Transfer |

Magento owns the homepage, shop, CMS content and blog. Laravel owns the service website under `/services` and its enquiry workflow. Blog articles have one Magento URL; a second Laravel article URL is not created.

## 1. Website status dropdown: redirect to frontend

### What has been developed

The **Website status** dropdown includes these options:

| Option | Visitor experience | Administrator experience |
| --- | --- | --- |
| Online — visible to everyone | Normal website access | Normal website access |
| Offline — administrator preview only | Branded temporary offline page | Use **Open private preview** after signing in |
| Redirect to frontend — signed-in administrators | Branded temporary offline page | Existing Magento Admin login automatically permits frontend access in the same browser |

In **Redirect to frontend** mode, an administrator can open the homepage, a product, an article or a `/services` page without first pressing the manual preview button. The requested frontend address is preserved. Saving this mode also establishes frontend access for the administrator making the change.

The browser checks the existing Magento Admin session through the Admin cookie path. Access is granted only after that session is recognised. A normal customer login or Laravel staff login does not grant this access.

### How to use it

1. Sign in to Magento Admin in the browser you will use for the website.
2. Open **Content → Website Online / Offline**.
3. Select **Redirect to frontend — signed-in administrators**.
4. Set the visitor message if required, then select **Save website status**.
5. Open the frontend in the same browser. Visitors without an administrator session continue to see the offline page.

### Access rules and limits

- Changing the status requires the **Website Online / Offline** role permission.
- Authenticated Magento administrators can obtain preview access without permission to change the site's status.
- Automatic recognition requires JavaScript and the same browser's Admin session.
- Access ends when the Admin session is revoked/expires or the administrator logs out. Disabled accounts and changed passwords lose access.
- Switching between manual preview and automatic access invalidates previews issued for the previous mode.
- Enabled Magento two-factor authentication remains part of the normal Admin login.
- API/Ajax requests remain offline until frontend access has been established through a page visit.

The wider offline behaviour, response codes and manual preview workflow are covered in point 6.

## 2. Frontend page builder investigation and implementation

### Investigation outcome

Two editing routes are available in the project:

| Editing route | Purpose | Access |
| --- | --- | --- |
| Native Magento Page Builder | Edit CMS rows, columns, text, images, buttons and native widget settings inside Admin | Content → Shi3ld Website Pages → page → Content → Edit with Page Builder |
| Shi3ld frontend visual editor | Edit content against the website layout, with drafts, private preview, revisions and reusable designs | Open frontend visual editor from Shi3ld Website Pages or an authorised frontend shortcut |

The result goes beyond investigation: a custom visual editing system has been implemented and extended with advanced design features. It provides WordPress/Elementor-style editing interactions within this Magento/Laravel project. It does not install WordPress or provide compatibility with Elementor plugins or template files.

### Content and layout controls developed

- Select and edit headings, paragraphs, links, images and buttons in an isolated website canvas.
- Add prepared text, image/text, two-column, call-to-action and FAQ sections.
- Reorder, duplicate and remove sections; use drag controls or ordering buttons.
- Use nested containers, grid/flex layouts and width controls.
- Manage elements through a nested navigator, custom labels and CSS classes.
- Lock content, select multiple elements with Shift, and copy, paste or reset styles.
- Use up to 60 undo steps during an editing session.
- Choose desktop, tablet and mobile preview widths and separate responsive appearance values.
- Set typography, colours, spacing, alignment, borders, shadows and hover/focus appearance.

### Images and interactive content

- Select existing media or upload JPEG, PNG, GIF and WebP images through the custom editor, up to 5 MB per image.
- Edit image descriptions, fit, aspect ratio and focal position; choose media backgrounds and gradients.
- Replace the brand mark with an image.
- Insert tabs, image galleries/lightboxes, icon lists and navigation sections.
- Add click-to-load YouTube/Vimeo video content.
- Apply entrance effects that respect reduced-motion preferences.

Image appearance controls change presentation; they do not crop the uploaded original. Dynamic catalogue widgets and service forms are rendered by their owning application and protected from ordinary inline record editing.

### Reusable designs, templates and CSS

The **Builder Design Library** supports sections, page templates/copies, global brand designs, article cards, popups, forms and product arrangements.

- Insert a separate copy of a saved design, or link a published section/form so future publication of the original updates its instances.
- Apply page templates using a **Page content slot**, preserving the page's content inside the template.
- Target header/footer templates explicitly to their region.
- Set display rules by included/excluded paths, wildcard paths, the local application's audience and UTC dates. Template priority controls competing matches.
- Manage shared colours, fonts, widths, spacing and button appearance through Magento's global brand designs.
- Bind approved dynamic fields such as article title/image/URL and product or service details.
- Create custom article card designs for use by the blog widget.
- Create product arrangements with the gallery left, right or above native product information. Magento retains pricing, variant selection and purchasing behaviour.
- Add popups or announcement banners with delay, scroll or click triggers and dismissal frequency controls.
- Create reusable service enquiry forms with additional fields, using the existing Laravel enquiry inbox and notification workflow. Editor previews disable submission.

Custom CSS has page, element and global scopes. Use `selector` for the current scope. The editor includes highlighting, property completion, and support for `@media` and `@supports`. CSS imports, URL functions, scripts, font-face declarations and keyframes are excluded; backgrounds use the media picker.

### Drafts, review and permissions

- **Save draft** stores working content separately from publication.
- **Preview draft** opens a private, session-bound preview; Magento draft previews last 15 minutes and are marked noindex.
- **History** shows up to 100 recent draft/publication snapshots. An older revision can be restored as a new draft.
- Concurrent draft versions and changes made through native CMS editing are checked before publication.
- Browser-tab recovery can retain unsaved changes after a reload.
- Review requests, side-by-side comparisons and scheduled publication are available. Schedules use UTC and the running schedulers; changing a scheduled draft prevents the older version from being published.
- Edit, design and publication permissions are separate. Server checks restrict layout/style changes for content-only editors.
- The review tool highlights basic heading, image-description, label, contrast, SEO-description and page-size issues. It provides guidance rather than accessibility certification.

Article editors additionally have server autosave, internal notes and editing presence, described in point 3. Server autosave should not be assumed for every CMS page editor.

### Laravel service editing

Laravel has the matching editor under **/services/staff/login → Service page content → Open visual editor**, plus its own design library. It covers the service homepage, service detail pages and information pages.

Published Magento header/footer and shared design settings are made available to Laravel through read-only shared manifests. Laravel service content and its own designs are stored separately. Magento Admin and Laravel staff sessions remain separate; the offline preview gate still applies across both applications.

## 3. Blog articles: categories, featured image and Read more

### Core article management developed

**Content → Blog Articles** creates an unpublished article and opens its visual editor. The **Page** tab includes:

- Article title and URL key.
- SEO title and meta description.
- Excerpt/summary used in article cards.
- Author and featured-article flag.
- Assignment to multiple blog categories.
- Featured/main image and alternative text.
- Article body composed with the visual content tools.

Blog categories are managed under **Content → Blog Categories**. They are separate from shop categories and support parent categories, ordering, descriptions, SEO fields and an active/hidden state. Assignments are retained when a category is hidden.

The featured image appears in article presentation and configurable listing cards. Responsive thumbnails are generated, and a branded fallback is available when an image is missing. Article cards can show an excerpt and a **Read more** link/button leading to the full article. The label and visibility of that link are configurable in the widget.

### Public addresses

| Page | Address pattern |
| --- | --- |
| Blog listing | `/blog` |
| Article | `/blog/<article-key>` |
| Category archive | `/blog/category/<category-key>` |
| Author archive | `/blog/author/<author-key>` |
| Tag archive | `/blog/tag/<tag-key>` |

For example, the local blog listing is <https://shi3ld.rpidevserver/blog>. Magento handles these routes, metadata, canonical links and URL rewrites.

### Additional blog development delivered

The **Blog Management** screen and article editor's **Blog** tab extend the initial requirement:

| Area | Implemented capabilities |
| --- | --- |
| SEO review | Search/social previews, duplicate metadata checks, missing images/alternative text, heading checks, review reminders and checks of up to 50 internal links per article. Service links use the local services sitemap; external sites are not crawled. |
| Authors | Profiles with biography, expertise and image, public author archives and structured author data. |
| Taxonomy | Nested categories and article tags/topics with public archives. |
| Search | Keyword relevance, category, tag, author and publication-date filters. Filtered search pages are noindex. |
| Related articles | Automatic suggestions, ordered pinned articles and explicit exclusions. |
| Commerce/services | Selected Magento product cards using native pricing, service links and signed attribution into Laravel enquiries. |
| Reading experience | Heading anchors, optional table of contents, estimated reading time and optional reading progress. |
| Starter content | How-to, buying guide, comparison and case-study draft starters. |
| Editorial calendar | Writer assignments, deadlines, review/retirement dates, calendar date changes and the review/scheduled-publication workflow. Dates use UTC. |
| Collaboration | Idle server autosave for article drafts, internal notes, version checks and 90-second editing leases refreshed every 30 seconds. Publishers can explicitly take over a lease. |
| Sharing | Social title/description/image overrides and public share/copy-link buttons. |
| Subscriptions/RSS | Confirmed subscriptions with category preferences, owner preference/unsubscribe forms, daily/weekly digests and RSS at `/blog/rss/index`. Digests contain up to 24 recent matching articles. |
| Analytics | Aggregate reads, product/service clicks, helpfulness and completed Laravel enquiry attribution. Signed conversions use a retrying queue; acknowledged source articles appear in the staff enquiry view. |
| Reader feedback | Helpfulness votes and moderated plain-text comments with a honeypot and request limits. Comments require both the global setting and the article's permission. |
| Maintenance/transfer | Bulk assignments/topics/publishing, review and retirement dates, CSV summaries and JSON article/media packages. Packages support up to 50 selected articles and 20 MB; imports create drafts and report collisions/skipped references. |
| Languages | Article/category translations for configured secondary Magento store views, separate publication, translated URL keys and published-language hreflang links. Content is translated by editors; it is not translated automatically. |

Anonymous metrics are approximate and deduplicated using a keyed IP hash and day. The module does not retain raw IP addresses for those counts; DNT/GPC and private previews skip automatic tracking. There is no simultaneous character-by-character co-editing.

**Comments, subscriptions and email delivery start disabled.** A publisher enables them under **Blog Management → Subscriptions**. Development email is configured for Mailpit. Analytics/rejected-feedback retention defaults to 180 days and can be set between 30 and 730 days. Scheduled publication uses the existing minute scheduler; retirement/blog maintenance runs every five minutes.

### How to publish an article

1. Create any required categories and author profile.
2. Open **Blog Articles**, create the article and enter its title.
3. In **Page**, set categories, featured image/alternative text, excerpt and SEO fields.
4. Compose the body; use **Blog** for related content, author/tags, sharing and editorial settings.
5. Save the draft, review the private preview and SEO report, then publish or schedule it with publication permission.
6. Add the article widget to the homepage or another page as described below.

Only published articles appear to visitors, public widgets and sitemaps. Unpublishing hides an article while retaining its content/history and reserved historical URL paths.

## 4. Article shortcode/widget, responsive layouts and automatic SEO URLs

### What has been developed

The shortcode requirement is implemented using a native Magento widget: **Shi3ld Blog Articles**. The widget can be configured through a visual interface and reused on the homepage or another CMS page.

Three insertion routes are available:

1. **Frontend editor → Add → Blog articles** for visual insertion and settings.
2. **Content → Blog Widget Builder** for settings, responsive previews and a generated directive to paste into a Page Builder HTML Code element.
3. Native **Insert Widget → Shi3ld Blog Articles** inside supported CMS editing fields.

### Configuration available

| Setting | Supported values/behaviour |
| --- | --- |
| Categories | Multiple blog categories; no selection means all. Matching is any selected category, with no repeated article cards. Parent-category filtering includes active descendants. |
| Number of articles | 1–24; default 6. |
| Desktop columns | 1–4; default 3. |
| Tablet columns | 1–3; default 2. |
| Mobile columns | 1–2; default 1. |
| Image position | Above text, below text, beside text, or text only. |
| Display design | Cards, a single-column list, or a featured first article followed by a grid. |
| Article order | Newest, oldest, featured articles only, or manual article IDs in display order. |
| Excerpt | Show/hide; configurable length from 0–500 characters. |
| Metadata | Independently show/hide categories, date and author. |
| Read more | Show/hide; custom label; text-link or button appearance. |
| Section controls | Heading and optional View all articles link. |
| Image shape | Landscape, square or portrait. |
| Appearance | Compact/normal/relaxed spacing and white/soft-green/dark-green backgrounds. |
| Custom card design | Select a published card design from the Builder Design Library. |

Responsive column settings, image sizing and thumbnails adapt the section to the device. List layouts intentionally use one column. The section is hidden when no matching published articles exist.

### Example: six articles with the image below the text

The following illustrates the available directive. Replace example category IDs `1,2` with the categories selected on your installation; the Widget Builder generates those values for you.

```text
{{widget type="Shi3ld\Blog\Block\Widget\Articles" heading="Latest advice" categories="1,2" limit="6" columns="3" tablet_columns="2" mobile_columns="1" layout="image-bottom" design="cards" sort="newest" show_read_more="1" read_more_label="Read more"}}
```

This displays up to six published matching articles, in three desktop columns, two tablet columns and one mobile column, with the featured image below the text and a Read more link. Widget category settings store IDs, so renaming a category does not break existing settings.

### Automatic slug creation and Magento SEO integration

- New article URL keys are generated from the title using Magento's `TranslitUrl` filter.
- Automatically generated duplicates receive a numeric suffix. A conflicting explicitly requested key requires correction.
- Changing an article title preserves its existing URL key unless an editor deliberately changes it.
- Publication writes native Magento URL rewrites. Historical article/category URLs redirect permanently to the latest published URL when the key changes.
- Category URL changes do not change article addresses.
- Public controller URLs redirect to the canonical article/archive address.
- Published content is included in the commerce sitemap, with article metadata, canonical links and structured data.
- Private draft previews and filtered search pages are noindex. Other public blog pages follow the store's robots configuration.
- Published translations can have their own URL keys and language links. Untranslated secondary views fall back to the original content with its default-view canonical URL.

These features implement Magento-compatible URL and metadata handling; they do not guarantee a search ranking.

## 5. Modify Magento pages through a WordPress-style CMS

### What can be managed

| Content | Editing route | Behaviour |
| --- | --- | --- |
| Homepage and other CMS pages | Native Page Builder or Shi3ld frontend editor | Edit sections, text, images, buttons and supported widgets. |
| Shop CMS page | Page editing with its live catalogue block | Reposition surrounding content while keeping the catalogue block for live product/category rendering. |
| Shared header and footer | Edit website header / Edit website footer | Publish Magento CMS block content and share it with Laravel services. |
| Blog articles | Article frontend editor | Use the same section/media tools with article-specific fields and workflow. |
| Laravel service pages | Service staff visual editor | Manage service content in Laravel with its own permissions and drafts. |

Native CMS pages retain their title, identifier/URL, metadata and Page Builder fields. Additional pages can be created through **Content → Pages → Add New Page**. The initial visual conversion patch preserves existing titles, addresses and metadata and does not reset later visual edits when upgrades run again.

### Two publication workflows

**Native Magento Page Builder:** open the page, edit its content, close the fullscreen canvas and save the CMS page. This updates published native CMS content and invalidates Magento's page cache.

**Shi3ld frontend editor:** open the visual editor, change the page, save a draft, inspect a private preview, then publish with the appropriate permission. History supports restoring an earlier snapshot as a new draft. The editor detects conflicting saves and published changes made outside its draft workflow.

The frontend editor's draft and preview workflow is the route for preparing changes before making them public. Native Page Builder remains available for its native content/widget settings.

### Scope of the WordPress-style experience

Administrators can perform the implemented content and layout tasks without editing template source files. The advanced controls are detailed in point 2.

Products and shop categories remain managed under **Catalog → Products / Categories**. Pricing, stock, customer accounts and checkout transactions stay under Magento's normal systems. Product layout designs arrange supported native regions; a full checkout flow is not replaced by arbitrary CMS sections.

Magento owns its CMS/blog/design schema through its modules. Laravel owns service content, drafts and enquiries through Laravel migrations. Shared publication does not give Laravel permission to alter Magento tables or merge the two login systems.

## 6. Switch the site offline while allowing administrator viewing

### What has been developed

The website access switch applies to Magento and Laravel `/services`, including service staff pages. It provides a branded offline page with an editable plain-text message of up to 500 characters.

- Anonymous visitors receive **503 Service Unavailable** with a `Retry-After` header while the site is offline.
- Magento Admin remains available so an authorised administrator can change the status again.
- Static/media assets remain available.
- The nginx access check runs before application page caches; cached pages cannot bypass the offline gate.
- Private preview HTML is marked private and is not stored in browser/shared page caches.
- Manual preview and automatic administrator access both cover the shop and services.

### Manual private preview workflow

1. Sign in to Magento Admin.
2. Open **Content → Website Online / Offline**.
3. Select **Offline — administrator preview only**, enter the visitor message and save.
4. Select **Open private preview** to view the website in the same browser.
5. Use **End my private preview** when finished, or return the site to **Online — visible to everyone** when ready.

Manual preview lasts one hour. It uses a secure, HttpOnly, host-only browser cookie and private server-side session records. Logging out, preview expiry, account disablement or password changes end access. Bringing the website online and taking it offline again invalidates previews from the earlier offline period.

For automatic access based on an existing Magento Admin login, use the **Redirect to frontend** option in point 1. Customer and Laravel staff credentials alone do not unlock the offline website.

### Operational scope

This switch supports private website review while the applications are running. Magento's native command-line maintenance mode is separate; administrator preview cannot make unavailable containers or an application in native maintenance mode serve normal pages. The implementation uses this project's configured Admin path, which must be updated in nginx if the backend front name changes.

## 7. Product synchronisation between environments

### What has been developed

**Catalog → Catalogue Transfer** provides a controlled catalogue promotion workflow:

```text
Development → export → Staging → preview/apply/review → export → Production → preview/apply
```

An administrator downloads a versioned ZIP from the source and uploads it on the destination. This is a deliberate transfer with validation and review. Continuous/background synchronisation, two-way conflict resolution and automatic remote server transfers are not configured.

### Supported catalogue content

- Simple and configurable products, including configurable child variants.
- Product names, descriptions, SEO fields and supported global/admin attributes.
- Basic custom attributes/options, attribute sets, weight, country and lead-time values.
- Category content and product/category assignments.
- Configurable SKU relationships, including supported size/colour options.
- JPEG, PNG and GIF gallery images and image roles.
- Optional packaged prices/tax-class data and inventory source quantities.

Products match by **SKU**, attributes by code, options by label, attribute sets by name and categories by their URL-key ancestry under the destination's default root. Source and destination database IDs do not need to match. Images are matched by content to avoid adding the same image repeatedly.

### Development → staging → production workflow

1. Deploy compatible Shi3ld code and apply each application's schema updates on the destination.
2. Set each server's private `SHI3LD_ENVIRONMENT` value to `development`, `staging` or `production`, and recreate the Magento application/cron containers when that value changes.
3. On development, open **Catalogue Transfer**, select approved parent SKUs or the default website catalogue, then export. Configurable variants are included automatically. Sample SKUs are excluded by default; prices and stock are opt-in.
4. Upload the ZIP on staging and run its preview. Review products to create/update, categories, images, attribute changes, warnings and validation errors.
5. Choose whether to apply packaged prices and stock, then apply the validated package. Review the resulting catalogue on staging.
6. Export the approved staging catalogue and upload it on production. Review the preview again and confirm the destination hostname before applying.

Direct development → production promotion is rejected. Production also rejects `SHI3LD-SAMPLE-` products. A dedicated **Catalogue Transfer** role permission controls the Admin workflow.

### Preservation and recovery behaviour

- Prices, tax classes and stock are preserved unless their import is explicitly selected. Existing product status, visibility and website assignments are preserved.
- New products remain disabled unless both price and stock import are selected; with both selected, their packaged status is used.
- Existing categories, memberships, gallery entries and products are retained when missing from a package.
- Destination inventory sources and required product tax classes/rules must be configured before import.
- Packages include versioned `catalogue.json`, images and SHA-256 integrity checks.
- Preview creates a private transfer record/package without modifying catalogue records. Previews expire after 24 hours.
- Apply saves a private snapshot of existing selected products, records saved SKUs and refreshes catalogue indexes, storefront caches and the sitemap.
- A transfer record cannot be applied twice. A partial failure records completed products and the error; it does not automatically undo completed saves.
- Keep full database/media backups for complete recovery. A selected-product snapshot alone does not remove every new record created by an import.

### Current transfer limits

The initial scope is one default website with global/admin product values and up to 1,000 products, including variants, per package. Admin upload size follows PHP's limit, currently approximately 20 MB; the package reader also enforces a 256 MB expanded limit.

Custom product options, videos, swatch definitions, tier/group prices, complex extension attributes and store-view overrides require separate handling. Orders, customers, reservations, administrator accounts, credentials and payment/shipping configuration are excluded from catalogue promotion.

Catalogue writes use Magento services, EAV setup APIs and Inventory APIs. Laravel migrations do not create or copy these product records. Environment schema deployment and catalogue content promotion are separate operations.

### Available CLI equivalents

Run these from the project directory. Replace the example SKU and package filename with the reviewed values; these commands are documented here and have not been executed as part of writing this report.

```bash
# Source: export approved products; prices/stock remain excluded by default.
docker compose exec -T php-magento php bin/magento shi3ld:catalog:export --sku=APPROVED-PARENT-SKU

# Destination: preview a package already copied into this container path.
docker compose exec -T php-magento php bin/magento shi3ld:catalog:import var/shi3ld/catalogue-transfer/catalogue-FILENAME.zip --dry-run

# Destination: apply while preserving existing prices and stock.
docker compose exec -T php-magento php bin/magento shi3ld:catalog:import var/shi3ld/catalogue-transfer/catalogue-FILENAME.zip --apply
```

Export flags `--include-prices` / `--include-stock` include those values; import flags `--prices` / `--stock` explicitly apply them. A production apply also needs `--confirm-host=<actual-production-hostname>`. Without `--apply`, the CLI performs a preview.

## Implementation locations and validation record

These references let another developer inspect the implementation without relying only on this report:

| Area | Main implementation |
| --- | --- |
| Website status and preview | [SiteAccess model](../magento/app/code/Shi3ld/Content/Model/SiteAccess.php), [preview sessions](../magento/app/code/Shi3ld/Content/Model/PreviewSession.php), [automatic Admin recognition](../magento/app/code/Shi3ld/Content/Controller/Adminhtml/Siteaccess/Frontend.php), [nginx routing](../docker/nginx/templates/default.conf.template) |
| CMS draft/revision workflow | [Magento editor documents](../magento/app/code/Shi3ld/Content/Model/Editor/Documents.php), [shared editor UI](../laravel/public/services/assets/content-editor.js) |
| Advanced page builder | [builder schema](../magento/app/code/Shi3ld/Content/Model/Builder/Schema.php), [advanced editor UI](../laravel/public/services/assets/builder-advanced.js), [runtime](../laravel/public/services/assets/builder-runtime.js) |
| Blog articles and URLs | [repository](../magento/app/code/Shi3ld/Blog/Model/Repository.php), [native URL handling](../magento/app/code/Shi3ld/Blog/Model/Urls.php), [article template](../magento/app/code/Shi3ld/Blog/view/frontend/templates/article.phtml) |
| Article widget | [supported options](../magento/app/code/Shi3ld/Blog/Model/WidgetOptions.php), [Magento widget definition](../magento/app/code/Shi3ld/Blog/etc/widget.xml), [widget rendering](../magento/app/code/Shi3ld/Blog/Block/Widget/Articles.php) |
| Advanced blog management | [management actions](../magento/app/code/Shi3ld/Blog/Model/Editorial/Studio.php), [management UI](../laravel/public/services/assets/blog-studio.js), [Laravel attribution](../laravel/app/Services/BlogAttribution.php) |
| Catalogue promotion | [exporter](../magento/app/code/Shi3ld/Catalog/Model/Transfer/Exporter.php), [importer](../magento/app/code/Shi3ld/Catalog/Model/Transfer/Importer.php), [environment rules](../magento/app/code/Shi3ld/Catalog/Model/Transfer/Environment.php), [transfer records](../magento/app/code/Shi3ld/Catalog/Model/Transfer/Jobs.php) |
| Deployment and maintenance instructions | [Project README](../README.md) |

The preceding development installation completed Magento dependency compilation, schema status checks, Laravel attribution migration, Blade compilation, PHP/JavaScript syntax checks, XML parsing, shared asset comparisons and nginx configuration validation. Sitemaps and shared design manifests were regenerated, workers refreshed, and all 11 project containers were running at that check. Mailpit configuration and read-only access to the private integration key were verified without sending email or displaying credentials.

Those checks do not demonstrate every browser workflow or a production rollout. Acceptance still needs to cover administrator/anonymous offline behaviour, responsive editing/widget presentation, draft/revision and scheduled-publication interactions, article/translation URLs, enquiry attribution, confirmation/digest delivery and a reviewed catalogue transfer on the actual destination environments. Creating this document does not change website settings, publish content, send messages or transfer products.
