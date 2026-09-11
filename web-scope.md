# Shi3ld Alliance — Website Business & Technical Proposal

**Project:** Shi3ld Alliance Website  
**Primary Revenue Streams:** Ecommerce and Physical Cleaning Services  
**Proposal Type:** Business, Functional and Technical Website Scope  
**Prepared:** September 2026   

---

## Contents

1. [Executive Summary](#1-executive-summary)
2. [Business Context and Project Vision](#2-business-context-and-project-vision)
3. [Project Objectives](#3-project-objectives)
4. [Target Audiences and User Groups](#4-target-audiences-and-user-groups)
5. [Platform and Framework Evaluation](#5-platform-and-framework-evaluation)
   - [5.1 Recommendation Summary](#51-recommendation-summary)
   - [5.2 Platform Comparison](#52-platform-comparison)
   - [5.3 Recommended Technical Direction](#53-recommended-technical-direction)
   - [5.4 Why Magento Open Source is Recommended](#54-why-magento-open-source-is-recommended)
   - [5.5 Why WordPress and WooCommerce Remain a Strong Alternative](#55-why-wordpress-and-woocommerce-remain-a-strong-alternative)
   - [5.6 BigCommerce](#56-bigcommerce)
   - [5.7 Shopware](#57-shopware)
   - [5.8 Shopify](#58-shopify)
   - [5.9 Laravel / Custom Application Approach](#59-laravel--custom-application-approach)
6. [Recommended Solution Architecture](#6-recommended-solution-architecture)
7. [Project Phasing Strategy](#7-project-phasing-strategy)
8. [Phase 0 — Discovery, Planning and Definition](#8-phase-0--discovery-planning-and-definition)
9. [Phase 1 — Public Website and Ecommerce Launch](#9-phase-1--public-website-and-ecommerce-launch)
   - [9.1 Website Information Architecture](#91-website-information-architecture)
   - [9.2 Ecommerce Scope](#92-ecommerce-scope)
   - [9.3 Product Catalogue and Product Data](#93-product-catalogue-and-product-data)
   - [9.4 Product Configuration Requirements](#94-product-configuration-requirements)
   - [9.5 Search, Categories and Filtering](#95-search-categories-and-filtering)
   - [9.6 Basket and Checkout](#96-basket-and-checkout)
   - [9.7 Payments](#97-payments)
   - [9.8 Shipping, Delivery and Lead Times](#98-shipping-delivery-and-lead-times)
   - [9.9 Customer Accounts and Order Management](#99-customer-accounts-and-order-management)
   - [9.10 Promotions, Cross-Selling and Reviews](#910-promotions-cross-selling-and-reviews)
10. [Phase 1 — Cleaning Services Website Scope](#10-phase-1--cleaning-services-website-scope)
    - [10.1 Domestic Cleaning](#101-domestic-cleaning)
    - [10.2 Commercial Cleaning](#102-commercial-cleaning)
    - [10.3 Care Home Cleaning](#103-care-home-cleaning)
    - [10.4 Service Enquiry and Quotation Forms](#104-service-enquiry-and-quotation-forms)
    - [10.5 Gallery, Testimonials and Trust Content](#105-gallery-testimonials-and-trust-content)
11. [Content Management and Marketing Requirements](#11-content-management-and-marketing-requirements)
12. [Brand, UX and Responsive Design](#12-brand-ux-and-responsive-design)
13. [Accessibility Requirements](#13-accessibility-requirements)
14. [SEO and Organic Search Scope](#14-seo-and-organic-search-scope)
15. [Analytics, Tracking and Conversion Measurement](#15-analytics-tracking-and-conversion-measurement)
16. [Security Requirements](#16-security-requirements)
17. [Privacy, Cookies, Ecommerce and Regulatory Considerations](#17-privacy-cookies-ecommerce-and-regulatory-considerations)
18. [Hosting, Infrastructure and Environments](#18-hosting-infrastructure-and-environments)
19. [Integrations](#19-integrations)
20. [Phase 2 — Cleaning Operations Platform](#20-phase-2--cleaning-operations-platform)
    - [20.1 Phase 2 Strategic Goal](#201-phase-2-strategic-goal)
    - [20.2 Customer Booking and Scheduling](#202-customer-booking-and-scheduling)
    - [20.3 Cleaner and Workforce Administration](#203-cleaner-and-workforce-administration)
    - [20.4 Operations and Dispatch](#204-operations-and-dispatch)
    - [20.5 Customer Portal](#205-customer-portal)
    - [20.6 Cleaner Portal / Mobile Experience](#206-cleaner-portal--mobile-experience)
    - [20.7 Commercial and Care Home Contract Management](#207-commercial-and-care-home-contract-management)
    - [20.8 Documents, Compliance and COSHH](#208-documents-compliance-and-coshh)
    - [20.9 Payments, Invoicing and Recurring Services](#209-payments-invoicing-and-recurring-services)
    - [20.10 Notifications and Communications](#2010-notifications-and-communications)
    - [20.11 AI Assistants and Automation](#2011-ai-assistants-and-automation)
    - [20.12 Reporting and Management Dashboards](#2012-reporting-and-management-dashboards)
21. [Phase 3 and Longer-Term Opportunities](#21-phase-3-and-longer-term-opportunities)
22. [Data Model and Integration Readiness](#22-data-model-and-integration-readiness)
23. [Administration and User Roles](#23-administration-and-user-roles)
24. [Testing and Quality Assurance](#24-testing-and-quality-assurance)
25. [Deployment and Launch Plan](#25-deployment-and-launch-plan)
26. [Training and Handover](#26-training-and-handover)
27. [Support, Maintenance and Continuous Improvement](#27-support-maintenance-and-continuous-improvement)
28. [Estimated Timescales](#28-estimated-timescales)
29. [Project Deliverables](#29-project-deliverables)
30. [Client Inputs and Responsibilities](#30-client-inputs-and-responsibilities)
31. [Assumptions](#31-assumptions)
32. [Out of Scope / Separately Scopable Items](#32-out-of-scope--separately-scopable-items)
33. [Risks and Mitigations](#33-risks-and-mitigations)
34. [Acceptance Criteria](#34-acceptance-criteria)
35. [Recommended Next Steps](#35-recommended-next-steps)
36. [Conclusion](#36-conclusion)

---

<a id="1-executive-summary"></a>
## 1. Executive Summary

Shi3ld Alliance requires a new website that supports two distinct but complementary revenue streams:

1. **Ecommerce** — initially focused on assistive grab rails and related accessibility products, with a requirement for configurable products, product specifications, stock and lead-time management, online payment and fulfilment.
2. **Physical Cleaning Services** — covering domestic, commercial and care-home cleaning, initially as a marketing and enquiry-led service, but with a planned second phase that evolves into a comprehensive booking, workforce and client-management platform.

The website should therefore not be treated as a simple brochure site with an online shop attached. The technology choice should support the immediate need to launch quickly while avoiding a platform decision that becomes restrictive when Shi3ld Alliance introduces operational scheduling, staff management, recurring cleaning bookings, customer portals and AI-assisted automation.

### Recommended approach

The recommended solution is:

- **Magento Open Source as the Phase 1 public website, ecommerce engine and CMS layer.**
- A **custom, responsive frontend/theme** designed for Shi3ld Alliance rather than a heavily modified off-the-shelf template.
- Magento CMS/Page Builder for public service pages, promotional landing pages, blog/resource content and campaign pages.
- A **modular Phase 2 service-management application**, preferably built with a framework such as **Laravel**, integrated with Magento through APIs.
- A shared visual identity and, where appropriate, shared authentication so the customer experiences one Shi3ld Alliance digital platform even where different backend components are used.

This architecture is recommended because Magento is particularly strong for products with configurable variants, individual SKUs, inventory and product attributes, while a dedicated application framework is more appropriate for complex operational workflows such as staff calendars, cleaner allocation, recurring bookings, job completion and AI-assisted scheduling.

The recommendation deliberately avoids forcing all future functionality into ecommerce extensions. The public website can remain stable while operational services are expanded independently.

### Delivery philosophy

The programme should be delivered in phases:

- **Phase 0:** Discovery, data design, technical planning and UX definition.
- **Phase 1:** Launch-ready website, ecommerce shop and cleaning service marketing/enquiry area.
- **Phase 1.5:** Post-launch optimisation and additional products such as anti-ligature rails and colostomy shelves.
- **Phase 2:** Cleaning operations platform, bookings, calendars, staff management, customer portal and AI-assisted administration.
- **Phase 3:** Advanced integrations, automation, manufacturer/stock connections, CRM/ERP integrations, route optimisation and deeper business intelligence.

This proposal intentionally contains **no financial costings**. Timescales are indicative and assume timely access to content, product information, branding, payment-provider accounts and client approvals.

---

<a id="2-business-context-and-project-vision"></a>
## 2. Business Context and Project Vision

Shi3ld Alliance is intended to combine physical products and service delivery under one brand. The website must communicate these two activities clearly without making the customer experience feel fragmented.

The ecommerce operation is expected to begin with approximately ten accessibility products, particularly grab rails, and grow shortly after launch. Product variants will include colours and potentially different dimensions, mounting arrangements, finishes or specifications. The catalogue should therefore be designed as a scalable product information structure from day one rather than as ten manually created static pages.

The cleaning division has a different customer journey. Users may initially be researching a service, checking whether Shi3ld Alliance operates in their area, reviewing trust and compliance information, comparing service types, and requesting a quotation. In Phase 2, this journey can become transactional, allowing suitable customers to book, manage and pay for services online.

The strategic vision is to create a platform that can become the primary digital operating environment for the business, not just its marketing website.

---

<a id="3-project-objectives"></a>
## 3. Project Objectives

The project should achieve the following objectives:

- Establish a professional and trustworthy Shi3ld Alliance digital brand.
- Launch a secure ecommerce store for accessibility products.
- Support configurable products without duplicating every colour or option as a separate visible product.
- Make specifications, dimensions, materials, installation information and lead times clear before purchase.
- Provide an excellent mobile shopping experience.
- Support customer accounts, orders, delivery information and transactional communications.
- Create a strong marketing presence for domestic, commercial and care-home cleaning.
- Generate qualified cleaning enquiries through structured quotation forms.
- Establish strong trust signals for care-home and commercial customers.
- Make the site straightforward for internal staff to update.
- Provide strong technical SEO foundations.
- Meet a high accessibility standard appropriate to a business selling accessibility products and services to potentially vulnerable or older users.
- Prepare the technical architecture for online service bookings, cleaner management, client portals and AI automation.
- Avoid unnecessary rebuilding when Phase 2 begins.

---

<a id="4-target-audiences-and-user-groups"></a>
## 4. Target Audiences and User Groups

The website should be designed for several audience types rather than a single generic customer.

### Ecommerce audiences

- Individuals purchasing accessibility products for themselves.
- Family members or carers buying products for another person.
- Occupational therapists or care professionals researching suitable products.
- Care homes and assisted-living organisations.
- Facilities managers.
- Installers and contractors.
- Trade or repeat purchasers if a trade-account model is introduced later.

### Cleaning service audiences

- Domestic households.
- Tenants and landlords seeking end-of-tenancy cleaning.
- Property managers and letting agents.
- Offices and retail businesses.
- Commercial and industrial customers.
- Residential and nursing care homes.
- Facilities-management organisations.
- Contract cleaning customers with multiple locations.

### Internal users

- Website administrators.
- Ecommerce administrators.
- Customer service staff.
- Marketing/content staff.
- Cleaning operations managers.
- Scheduling/dispatch staff.
- Cleaners and supervisors in Phase 2.
- Finance/management users in Phase 2.

The architecture and permission model should anticipate these user groups even where some roles are not activated until later phases.

---

<a id="5-platform-and-framework-evaluation"></a>
## 5. Platform and Framework Evaluation

<a id="51-recommendation-summary"></a>
### 5.1 Recommendation Summary

**Primary recommendation: Magento Open Source for Phase 1, supported by a purpose-built Laravel service-management application in Phase 2.**

This is a modular architecture rather than an attempt to make a single platform perform every role.

The Magento component would own:

- Product catalogue.
- Product variants/configurations.
- Ecommerce checkout.
- Orders.
- Customer ecommerce accounts.
- Promotions.
- Product content.
- Public CMS pages.
- Marketing/service landing pages.
- Blog/resources where implemented.
- Public website navigation and SEO pages.

The Phase 2 application would own:

- Cleaning bookings.
- Recurring schedules.
- Staff and cleaner availability.
- Shift/job allocation.
- Operational task lists.
- Cleaning service history.
- Customer service portal.
- Cleaner mobile workflow.
- Compliance records.
- Service-specific invoicing and operational reporting.
- AI-assisted booking and scheduling workflows.

<a id="52-platform-comparison"></a>
### 5.2 Platform Comparison

The following assessment is indicative and based on the requirements in this proposal. Scores are not vendor benchmarks; they are a project-specific suitability assessment on a five-point scale.

| Platform / Approach | Complex Product Configuration | Marketing / CMS | Phase 2 Extensibility | Admin Ease | API / Integration | Operational Overhead | Overall Fit |
|---|---:|---:|---:|---:|---:|---:|---:|
| **Magento Open Source** | 5/5 | 4/5 | 5/5 | 3/5 | 5/5 | 3/5 | **Excellent** |
| **WordPress + WooCommerce** | 4/5 | 5/5 | 4/5 | 5/5 | 4/5 | 4/5 | **Very Good** |
| **Shopware** | 5/5 | 4/5 | 5/5 | 4/5 | 5/5 | 3/5 | **Very Good / Excellent Alternative** |
| **BigCommerce** | 4/5 | 3/5 | 4/5 | 3/5 | 5/5 | 5/5 | **Good** |
| **Shopify** | 4/5 | 4/5 | 3/5 | 5/5 | 4/5 | 5/5 | **Good** |
| **Laravel + Bagisto / Custom Commerce** | 5/5 | 3/5 | 5/5 | 3/5 | 5/5 | 2/5 | **Powerful but Higher Build Burden** |
| **Drupal Commerce** | 4/5 | 5/5 | 5/5 | 2/5 | 5/5 | 2/5 | **Capable but Less Appropriate Operationally** |

<a id="53-recommended-technical-direction"></a>
### 5.3 Recommended Technical Direction

For Shi3ld Alliance, the platform decision should be based on the **combined five-year shape of the business**, not only the ten launch products.

The recommended direction is:

> **Magento Open Source for commerce and public content, with a separately engineered Laravel-based service operations layer added in Phase 2.**

This gives the company a mature product catalogue and ecommerce model while preserving freedom to build bespoke service workflows without turning Magento into an employee scheduling system.

It is important to note that **the initial catalogue size of approximately ten products, by itself, would not justify Magento**. WordPress/WooCommerce could comfortably handle that volume and the stated three-colour variations. The Magento recommendation is strategic: it becomes more compelling if Shi3ld Alliance expects materially more complex product configuration, manufacturer or stock integrations, a larger catalogue, trade/B2B requirements, multiple inventory sources or sophisticated merchandising. Phase 0 should therefore include a formal platform decision gate rather than treating the recommendation as predetermined.

A practical decision rule is:

- Choose **Magento Open Source** if product complexity, integration depth and future commerce capability are the stronger long-term drivers.
- Choose **WordPress + WooCommerce** if the product range remains relatively modest and marketing/content simplicity is the dominant requirement.
- Keep **Shopware** as the strongest ecommerce-first alternative if the project team wants to compare a modern commerce administration experience against Magento.

Regardless of which of these three wins Phase 0, the Phase 2 cleaning operations system should remain a separate modular application rather than being implemented as a collection of CMS plugins.

A single public domain and consistent design should conceal the technical separation from customers. The customer should see one Shi3ld Alliance experience.

<a id="54-why-magento-open-source-is-recommended"></a>
### 5.4 Why Magento Open Source is Recommended

Magento is particularly well aligned with the ecommerce part of the brief because configurable products can represent one parent product while each variation is managed as a distinct saleable SKU. This is valuable where a grab rail may exist in white, blue and dark grey, or later have combinations of dimensions, materials, fittings or other options.

Strengths relevant to Shi3ld Alliance include:

- Strong configurable-product model.
- Variant-specific SKU, stock, image, price and inventory management.
- Structured product attributes for dimensions, materials, compliance information and technical specifications.
- Category and layered navigation capabilities.
- Product relationships, including related products, cross-sells and upsells.
- Customer accounts and order history.
- Promotional rules.
- SEO controls at product/category/page level.
- CMS pages and reusable content blocks.
- Page Builder for richer service and campaign pages.
- REST and GraphQL APIs for future integrations.
- Good fit for future manufacturer, PIM, ERP or stock integrations.
- Full codebase control with Magento Open Source.

Trade-offs include:

- More specialist development knowledge is required than WordPress.
- Hosting and maintenance requirements are greater than a fully hosted SaaS platform.
- Admin users may need more structured training.
- Extensions should be selected carefully to avoid unnecessary complexity.

For this project, these trade-offs are acceptable because product configuration and future systems integration are strategically important.

<a id="55-why-wordpress-and-woocommerce-remain-a-strong-alternative"></a>
### 5.5 Why WordPress and WooCommerce Remain a Strong Alternative

WordPress and WooCommerce would be the preferred alternative if the client places the greatest emphasis on editorial simplicity and rapid marketing-page management.

Advantages include:

- Familiar WordPress administration.
- Excellent marketing and editorial experience.
- Large plugin ecosystem.
- WooCommerce variable products support independent prices, SKUs and stock for variations.
- Strong availability of booking plugins and integrations.
- Easier initial content publishing for non-technical staff.
- Large developer and agency ecosystem.

Concerns for this particular roadmap are:

- The more Phase 2 operational functionality that is added as plugins, the more the website risks becoming a collection of interdependent extensions.
- Complex workforce scheduling, cleaner assignment and compliance management are better implemented as a dedicated application.
- Advanced configurable product scenarios can require multiple plugins or custom development.
- Plugin maintenance and compatibility must be managed carefully.

If Shi3ld Alliance chooses WordPress, the recommended architecture would still be **WordPress/WooCommerce for public content and ecommerce plus a separate Phase 2 operational application** rather than attempting to run the complete workforce platform inside WordPress.

<a id="56-bigcommerce"></a>
### 5.6 BigCommerce

BigCommerce is a credible ecommerce platform with strong API capability and support for variants, product options and inventory. Its SaaS model reduces infrastructure maintenance.

Advantages:

- Hosted infrastructure.
- Strong ecommerce APIs.
- Product variants and options.
- Good checkout foundation.
- Useful for organisations that do not want to maintain the ecommerce server stack.

Concerns:

- CMS and editorial workflows are less natural than WordPress for a service-heavy marketing site.
- Licence/subscription structure becomes part of long-term operating cost.
- Custom service operations would still need a separate application.
- Client familiarity appears lower than with WordPress.

BigCommerce would become more attractive if Shi3ld Alliance prioritised SaaS operations and wanted minimal responsibility for ecommerce infrastructure.

<a id="57-shopware"></a>
### 5.7 Shopware

Shopware should be considered as the strongest additional alternative to Magento.

Relevant strengths include:

- Strong product and variant model.
- Modern administration.
- Shopping Experiences for content-rich landing, product and category pages.
- Rules and automation capabilities.
- API-led architecture.
- Good suitability for a business expecting sophisticated ecommerce logic.

Shopware may offer a more modern administrative experience than Magento in some areas, but Magento has stronger alignment with the client’s stated preference and a very mature ecosystem. If platform selection remains open after discovery, Magento and Shopware should be the final two ecommerce-first candidates.

<a id="58-shopify"></a>
### 5.8 Shopify

Shopify is easy to administer, hosted and fast to launch. It supports product variants and a large app ecosystem.

It is less suitable as the primary recommendation because:

- Advanced custom operational logic can become app-dependent.
- Complex Phase 2 workflows will still require an external application.
- Platform restrictions may create friction for deeply bespoke features.
- Long-term dependence on platform and app subscriptions should be considered.

Shopify remains appropriate where speed, simplicity and reduced technical ownership are more important than architectural control.

<a id="59-laravel--custom-application-approach"></a>
### 5.9 Laravel / Custom Application Approach

A complete custom platform could be built in Laravel, potentially with an ecommerce framework such as Bagisto, or with a bespoke commerce model.

This provides maximum flexibility but requires more functionality to be designed, developed and maintained that mature ecommerce platforms already provide.

A better use of Laravel for Shi3ld Alliance is therefore **Phase 2 operations**, where the workflows are unique to the business and bespoke development creates genuine competitive advantage.

---

<a id="6-recommended-solution-architecture"></a>
## 6. Recommended Solution Architecture

The proposed architecture should be modular.

### Phase 1 architecture

**Public website and shop**

- Magento Open Source.
- Custom Shi3ld Alliance theme.
- Magento Page Builder/CMS.
- Ecommerce catalogue and checkout.
- Secure payment provider integration.
- Shipping configuration/integration.
- Customer accounts.
- Transactional email service.
- Analytics and consent management.

### Phase 2 architecture

**Service operations application**

- Laravel backend/API.
- Dedicated relational database for operational service data.
- Web-based operations admin.
- Customer service portal.
- Cleaner/staff PWA or mobile-responsive application.
- Calendar/scheduling engine.
- Background job/queue processing.
- Notification service.
- AI orchestration layer.
- Integration with accounting, CRM, maps and communications services where required.

### Integration between platforms

The integration layer should support:

- Single sign-on or linked customer identities where justified.
- Customer profile synchronisation.
- Shared brand/navigation.
- Service links within ecommerce customer accounts.
- Order/customer references where cross-selling products and services.
- Shared analytics identifiers where compliant.
- API-driven data exchange rather than direct database coupling.

### Architectural principle

Magento and the service platform should not directly manipulate one another’s databases. Integrations should use versioned APIs and event/webhook patterns. This keeps upgrades safer and reduces future technical debt.

---

<a id="7-project-phasing-strategy"></a>
## 7. Project Phasing Strategy

A phased delivery is strongly recommended so Shi3ld Alliance can begin generating revenue while more sophisticated service operations are developed.

| Phase | Primary Objective | Indicative Outcome |
|---|---|---|
| Phase 0 | Discovery and planning | Agreed architecture, sitemap, UX, product data and scope |
| Phase 1 | Website + ecommerce + service marketing | Public launch and online product sales |
| Phase 1.5 | Optimisation and catalogue growth | Anti-ligature products, colostomy shelves, CRO and SEO improvements |
| Phase 2A | Booking and operations MVP | Online bookings, calendars, customers, cleaners and allocations |
| Phase 2B | Portals and automation | Customer portal, staff portal, communications and recurring services |
| Phase 2C | AI-assisted operations | Booking assistant, scheduling suggestions, exception handling |
| Phase 3 | Advanced business platform | ERP/PIM, routing, BI, manufacturer integrations, advanced automation |

---

<a id="8-phase-0--discovery-planning-and-definition"></a>
## 8. Phase 0 — Discovery, Planning and Definition

Phase 0 should remove ambiguity before build work begins.

### Discovery workshops should confirm

- Company positioning and brand values.
- Ecommerce product range at launch.
- Future product range.
- Product variant rules.
- Product specifications and attribute model.
- Stock ownership and stock source.
- How lead times are determined.
- Shipping methods and geographical limits.
- Returns requirements.
- Payment methods.
- VAT treatment.
- Cleaning service geographical coverage.
- Types of cleaning jobs accepted.
- Quote qualification rules.
- Care-home trust/compliance messages.
- Phase 2 booking rules.
- Operational staff structure.
- Customer account expectations.
- Reporting requirements.
- Existing systems to integrate.

### Phase 0 deliverables

- Final technical architecture.
- Approved sitemap.
- Page inventory.
- Product data schema.
- User journey maps.
- Wireframes for key page types.
- Integration register.
- Analytics plan.
- SEO migration plan if replacing an existing website.
- Content responsibility matrix.
- Phase 1 acceptance criteria.
- Phase 2 backlog at epic level.

---

<a id="9-phase-1--public-website-and-ecommerce-launch"></a>
## 9. Phase 1 — Public Website and Ecommerce Launch

<a id="91-website-information-architecture"></a>
### 9.1 Website Information Architecture

Recommended top-level structure:

- **Home**
- **Shop**
  - Grab Rails
  - Bathroom Support Rails
  - Toilet Support Rails
  - Folding / Hinged Rails
  - Wall-Mounted Rails
  - Anti-Ligature Grab Rails
  - Colostomy Shelves
  - Additional Accessibility Products
- **Cleaning Services**
  - Domestic Cleaning
  - Commercial Cleaning
  - Care Home Cleaning
- **Why Shi3ld Alliance / About Us**
- **Trust & Standards**
- **Gallery**
- **Reviews / Testimonials**
- **Advice / Resources / Blog**
- **Contact / Request a Quote**
- **My Account**
- Basket
- Checkout
- Search
- Legal and policy pages

The exact navigation should be validated during UX design. Product categories should be based on actual product taxonomy rather than duplicating similar products across multiple categories solely for navigation.

<a id="92-ecommerce-scope"></a>
### 9.2 Ecommerce Scope

Phase 1 ecommerce should include:

- Product catalogue.
- Categories and subcategories.
- Product filtering.
- Product search.
- Configurable product variants.
- Product images and galleries.
- Specifications.
- Dimensions.
- Technical information.
- Pricing.
- Stock state.
- Lead-time messaging.
- Delivery information.
- Basket.
- Checkout.
- Online payments.
- Customer accounts.
- Order history.
- Transactional email notifications.
- Reviews.
- Related products.
- Recommended products.
- SEO-ready product/category templates.
- Mobile-responsive purchasing flow.

<a id="93-product-catalogue-and-product-data"></a>
### 9.3 Product Catalogue and Product Data

The product catalogue should be structured around reusable attributes rather than free-text descriptions only.

Recommended attributes include:

- Product name.
- SKU.
- Parent/configurable SKU.
- Product category.
- Product type.
- Colour.
- Length.
- Width.
- Height.
- Diameter.
- Projection from wall where relevant.
- Maximum load/rating where legitimately specified by manufacturer.
- Material.
- Finish.
- Mounting type.
- Fixing information.
- Suitable environments.
- Product weight.
- Shipping weight.
- Pack dimensions.
- GTIN/EAN where available.
- Manufacturer reference.
- Warranty information.
- Technical documents/downloads.
- Installation instructions.
- Compliance/certification documents where applicable.
- Stock status.
- Lead time.
- Price.
- VAT class.
- SEO title.
- Meta description.
- Product URL.

Structured product data will make future product feeds, manufacturer integrations, trade catalogues and comparison tools much easier.

<a id="94-product-configuration-requirements"></a>
### 9.4 Product Configuration Requirements

Initial grab rails require three colour options:

- White.
- Blue.
- Dark grey.

The implementation should allow each colour variation to have, where required:

- Its own SKU.
- Its own stock quantity.
- Its own image.
- Its own lead time.
- Its own barcode/GTIN.
- Its own price where variants differ.

Future variants may include size, material, fitting type or additional attributes.

Product configuration should be designed so customers cannot select an impossible combination. If later products require more complex dependency rules, the product configuration component should be extendable without rebuilding the catalogue.

<a id="95-search-categories-and-filtering"></a>
### 9.5 Search, Categories and Filtering

Search and filtering should support customers who know what they need as well as those who require guidance.

Recommended filters include, where data exists:

- Product type.
- Colour.
- Size/length.
- Mounting type.
- Material.
- Application/location.
- Availability.
- Price range.

Search should include:

- Product names.
- SKUs.
- Product descriptions.
- Key attributes.
- Synonyms where necessary, for example alternative terminology for support rails.

For the initial small catalogue, filters should not be over-engineered. The data structure should nevertheless be created correctly so filters become useful as the range grows.

<a id="96-basket-and-checkout"></a>
### 9.6 Basket and Checkout

Checkout should minimise friction while still collecting information required for fulfilment.

Requirements:

- Guest checkout.
- Optional account creation.
- Existing customer login.
- Billing address.
- Delivery address.
- Delivery options.
- Clear delivery cost before payment.
- Promotion/coupon codes if enabled.
- Order summary.
- Tax/VAT presentation.
- Secure payment handoff.
- Order confirmation screen.
- Confirmation email.
- Terms acceptance where required.
- Validation and useful error handling.
- Mobile-first checkout layout.

The checkout must clearly communicate when an item has a manufacturing or extended lead time.

<a id="97-payments"></a>
### 9.7 Payments

The site should integrate with an established PCI-compliant payment provider.

Potential payment methods may include:

- Debit and credit cards.
- Apple Pay.
- Google Pay.
- PayPal where required.
- Alternative payment methods where commercially justified.

The website should avoid directly storing raw card data. Hosted or tokenised payment components should be preferred.

The final payment provider should be confirmed during discovery based on merchant account, transaction requirements, refund workflows and accounting integration.

<a id="98-shipping-delivery-and-lead-times"></a>
### 9.8 Shipping, Delivery and Lead Times

Shipping logic should be agreed before development because rails may have dimensions or weights that influence carrier selection.

The solution should support:

- Standard delivery.
- Expedited delivery if available.
- Free-delivery thresholds if introduced.
- Product-specific shipping restrictions.
- Postcode/region restrictions.
- Oversized item charges where needed.
- Different lead times by product or variant.
- Out-of-stock messaging.
- Back-order/pre-order rules where approved.

The website should distinguish:

- **In stock** — dispatch expectation.
- **Available to order** — manufacturing/supplier lead time.
- **Out of stock** — no current availability.
- **Pre-order** — where specifically enabled.

Delivery and lead-time language must avoid promising a dispatch date that the operation cannot reliably meet.

<a id="99-customer-accounts-and-order-management"></a>
### 9.9 Customer Accounts and Order Management

Customer account functionality should include:

- Registration.
- Login/logout.
- Password reset.
- Account details.
- Address book.
- Order history.
- Order detail.
- Re-order where useful.
- Communication preferences.

Phase 2 may extend the same identity into service bookings and service history.

<a id="910-promotions-cross-selling-and-reviews"></a>
### 9.10 Promotions, Cross-Selling and Reviews

Phase 1 should support:

- Discount codes.
- Promotional pricing.
- Related products.
- Accessories.
- Up-sells.
- Cross-sells.
- Featured products.
- New product collections.
- Review submission.
- Review moderation.

Review processes should prevent fabricated or misleading testimonials. Where review verification is introduced, the mechanism should be documented.

---

<a id="10-phase-1--cleaning-services-website-scope"></a>
## 10. Phase 1 — Cleaning Services Website Scope

The services section should initially focus on marketing, trust, qualification and enquiry generation.

<a id="101-domestic-cleaning"></a>
### 10.1 Domestic Cleaning

Pages/content should cover:

- Regular cleaning.
- One-off cleaning.
- Deep cleans.
- End-of-tenancy cleaning.
- Kitchen cleaning.
- Bathroom cleaning.
- Bespoke packages.

Each service page should provide:

- Description.
- Suitable customer profile.
- Typical tasks included.
- Optional/additional tasks.
- Frequency options.
- Service area.
- What the customer needs to prepare.
- Trust information.
- FAQ.
- Clear call-to-action.

<a id="102-commercial-cleaning"></a>
### 10.2 Commercial Cleaning

Content should cover:

- Offices.
- Retail premises.
- Communal areas.
- Industrial/commercial premises.
- Contract cleaning.
- Bespoke cleaning schedules.

Commercial pages should emphasise:

- Reliability.
- Defined service schedules.
- Account management.
- Site-specific requirements.
- Risk assessment/process capability where applicable.
- Out-of-hours cleaning where offered.
- Contract and recurring service capability.

<a id="103-care-home-cleaning"></a>
### 10.3 Care Home Cleaning

Care-home cleaning should have a dedicated content area because the buying criteria are different from ordinary domestic cleaning.

Content may include:

- Care-home cleaning services.
- Communal areas.
- Bedrooms/resident areas.
- Kitchens and dining areas.
- Bathrooms.
- High-touch-point cleaning.
- Scheduled/contract cleaning.
- Staff/service standards.
- Enquiry and quotation process.
- Cleaner screening/DBS statements where factually correct and legally appropriate.
- COSHH information.
- Cleaning product and chemical-management processes.
- Infection-control-oriented procedures where the business can substantiate them.
- Supervision and quality assurance.
- Escalation/contact process.

Claims must reflect the actual operating procedures of Shi3ld Alliance. The site should not make regulatory, clinical, infection-control or certification claims that cannot be demonstrated.

<a id="104-service-enquiry-and-quotation-forms"></a>
### 10.4 Service Enquiry and Quotation Forms

Phase 1 should include structured enquiry forms rather than only a generic contact form.

Recommended fields may include:

- Customer name.
- Organisation name.
- Email.
- Telephone.
- Postcode.
- Property/site type.
- Service category.
- Service frequency.
- Preferred contact method.
- Preferred start date.
- Approximate property/site size.
- Number of rooms/areas where relevant.
- Commercial/care-home site details.
- Free-text requirements.
- Optional file/photo upload if operationally useful.
- Consent/privacy confirmation.

The form should conditionally display fields based on service type so customers are not confronted with unnecessary questions.

Enquiries should:

- Be stored securely.
- Generate an internal notification.
- Generate a customer acknowledgement.
- Include a unique enquiry reference if useful.
- Be exportable or integrated with CRM later.
- Avoid collecting sensitive personal data unless it is genuinely required.

<a id="105-gallery-testimonials-and-trust-content"></a>
### 10.5 Gallery, Testimonials and Trust Content

The site should include reusable content for:

- Before/after cleaning images where consent exists.
- Service photography.
- Team imagery.
- Product installation examples where appropriate.
- Customer testimonials.
- Commercial case studies.
- Care-home case studies where permission exists.
- Accreditations and memberships.
- Insurance information where appropriate.
- Service standards.

---

<a id="11-content-management-and-marketing-requirements"></a>
## 11. Content Management and Marketing Requirements

The selected CMS should allow authorised staff to manage content without developer involvement for routine changes.

Content editors should be able to manage:

- Homepage sections.
- Service pages.
- Promotional landing pages.
- FAQs.
- Blog/resource articles.
- Images and media.
- Calls to action.
- Banners.
- Product/category supporting content.
- Trust statements.
- Gallery content.
- Testimonials.

Reusable content blocks should be created for repeated elements such as:

- Request a Quote calls to action.
- Delivery information.
- Trust/DBS/COSHH messaging.
- Customer support blocks.
- Newsletter signup.
- Service-area messages.

Content components should have design constraints so editors can create new pages without accidentally breaking layout consistency.

---

<a id="12-brand-ux-and-responsive-design"></a>
## 12. Brand, UX and Responsive Design

The website should have a bespoke Shi3ld Alliance visual system rather than appearing as a generic ecommerce template.

Design requirements include:

- Responsive layout from mobile to large desktop.
- Clear distinction between **Shop** and **Cleaning Services** while maintaining one brand.
- Simple primary navigation.
- Prominent search for ecommerce users.
- Prominent quote/service call-to-action for cleaning users.
- Consistent buttons and form elements.
- Readable typography.
- Strong colour contrast.
- Accessible interaction states.
- Product specifications that remain readable on small screens.
- Sticky or easily accessible basket/account tools where appropriate.
- Prominent telephone/contact options on service pages.

The home page should quickly establish that Shi3ld Alliance offers both accessibility products and professional cleaning rather than forcing users to infer the business model.

---

<a id="13-accessibility-requirements"></a>
## 13. Accessibility Requirements

Accessibility should be treated as a core quality requirement, not a final-stage test.

The recommended target is **WCAG 2.2 Level AA** for the public website and key Phase 2 customer journeys.

This is particularly important because the product audience may include people with disabilities, reduced mobility, visual impairment or age-related accessibility needs.

Requirements include:

- Semantic heading structure.
- Keyboard navigation.
- Visible focus states.
- Sufficient colour contrast.
- Text resizing without loss of function.
- Accessible form labels.
- Clear form errors and instructions.
- Alternative text for meaningful images.
- Captions/transcripts for meaningful media where applicable.
- Accessible modals and menus.
- Avoidance of interaction that relies only on colour.
- Accessible product swatches with textual labels.
- Appropriate ARIA only where native semantic elements are insufficient.
- Skip links.
- Logical tab order.
- Touch targets appropriate for mobile users.
- No critical information embedded only in imagery.

Testing should include automated accessibility tooling and manual keyboard/screen-reader checks on critical journeys.

---

<a id="14-seo-and-organic-search-scope"></a>
## 14. SEO and Organic Search Scope

SEO should be built into architecture and content rather than treated as a plugin installed before launch.

### Technical SEO

- Crawlable site architecture.
- Clean URLs.
- Canonical URLs.
- XML sitemaps.
- Robots controls.
- Redirect management.
- Correct status codes.
- Duplicate-content controls.
- Pagination/indexation strategy where required.
- Product structured data.
- Organisation/local-business structured data where appropriate.
- Service structured data where appropriate.
- Review markup only where compliant and supported by actual visible reviews.
- Breadcrumb structured data.
- Optimised image delivery.
- Core Web Vitals considerations.

### On-page SEO

- Unique product titles.
- Product descriptions that are not copied wholesale from manufacturers where avoidable.
- Category introductions.
- Service landing pages by real service proposition.
- Clear H1/H2 structure.
- Meta titles and descriptions.
- Image alt text.
- Internal linking.
- FAQ content.

### Local service SEO

Where Shi3ld Alliance operates in defined geographical areas, local SEO can include:

- Genuine service-area pages where content can be unique and useful.
- Google Business Profile optimisation.
- Consistent business details.
- Local reviews.
- Location/service schema where appropriate.

The site should avoid mass-producing thin location pages solely to target search engines.

### Content strategy

Potential long-term content themes include:

- Choosing the correct type of grab rail.
- Grab-rail positioning guidance with suitable professional disclaimers.
- Accessibility bathroom planning.
- Product maintenance.
- Care-home cleaning standards and processes.
- Cleaning checklists.
- Commercial contract-cleaning guidance.
- End-of-tenancy preparation.

---

<a id="15-analytics-tracking-and-conversion-measurement"></a>
## 15. Analytics, Tracking and Conversion Measurement

A measurement plan should be defined before launch.

Recommended events include:

### Ecommerce

- Product view.
- Search.
- Filter use.
- Add to basket.
- Remove from basket.
- Begin checkout.
- Payment success/failure.
- Purchase.
- Coupon use.
- Account registration.

### Services

- Service page view.
- Request-a-quote click.
- Quote form started.
- Quote form completed.
- Phone-number click.
- Email click.
- Care-home enquiry.
- Commercial enquiry.
- Domestic enquiry.

### Recommended tools

- Analytics platform such as GA4 or an approved alternative.
- Google Search Console.
- Tag management where required.
- Consent management.
- Optional heat-mapping/session tools only where privacy controls are appropriate.

Tracking should respect cookie and privacy choices.

---

<a id="16-security-requirements"></a>
## 16. Security Requirements

Security should be included throughout development and operations.

### Application security

- Supported software versions.
- Security patch process.
- Dependency monitoring.
- Input validation.
- Output encoding.
- CSRF protections.
- Secure session handling.
- Brute-force protections.
- Rate limiting on sensitive endpoints.
- Anti-spam/bot protection for public forms.
- Secure file-upload controls.
- Webhook validation.
- Secrets stored outside source code.

### Administrative security

- Multi-factor authentication for privileged users where supported.
- Least-privilege roles.
- Named administrator accounts rather than shared credentials.
- Strong password policy.
- Audit logging for sensitive operations where practical.
- Immediate removal of leavers’ access.

### Infrastructure security

- TLS/HTTPS across the site.
- Web application firewall/CDN where appropriate.
- Server hardening.
- Restricted administration services.
- Encrypted backups.
- Database access restricted to application networks.
- Monitoring and alerting.
- Malware/file-integrity monitoring where appropriate.

### Payment security

- Use a compliant payment provider.
- Avoid storing raw card details.
- Keep payment scope as small as reasonably possible.
- Use signed/validated payment webhooks.

---

<a id="17-privacy-cookies-ecommerce-and-regulatory-considerations"></a>
## 17. Privacy, Cookies, Ecommerce and Regulatory Considerations

This section is a technical/business scope and is not a substitute for legal advice. Final wording should be reviewed by the client and, where necessary, its legal adviser.

### Privacy

The website will process personal data through:

- Customer accounts.
- Orders.
- Enquiry forms.
- Newsletter/marketing forms.
- Analytics.
- Phase 2 bookings.
- Staff systems in later phases.

The project should include:

- Privacy notice.
- Clear purpose notices on forms where appropriate.
- Defined data retention periods.
- Processor/vendor register support.
- Data subject request process.
- Secure deletion/retention processes.
- Marketing consent capture where required.

### Cookies and similar technologies

The website should:

- Categorise cookies/trackers.
- Set strictly necessary cookies without unnecessary consent friction.
- Block or control non-essential tracking according to applicable rules.
- Offer meaningful consent choices.
- Store consent records where required.
- Make it easy for users to revisit cookie choices.

### Ecommerce information and consumer rights

The ecommerce journey should make relevant information available before purchase, including:

- Business identity/contact details.
- Product description.
- Total price.
- Taxes.
- Delivery options and charges.
- Delivery/lead-time information.
- Payment methods.
- Cancellation/returns information.
- Terms and conditions.

The client should provide or legally approve:

- Terms and conditions of sale.
- Returns/refunds policy.
- Delivery policy.
- Warranty information.
- Privacy policy.
- Cookie policy.
- Complaints/contact process.

### Cleaning service terms

Before transactional service bookings are introduced, Phase 2 should define:

- Cancellation policy.
- Rescheduling policy.
- Access/key-holder arrangements.
- Customer responsibilities.
- Minimum service duration where applicable.
- Recurring contract terms.
- Damage/reporting process.
- Service exclusions.

### DBS statements

DBS eligibility depends on the role and work being performed. Care-home cleaning can in some circumstances be eligible for Standard or Enhanced checks depending on frequency and contact. Website statements must therefore reflect actual checks undertaken for the relevant workforce rather than using a blanket claim that cannot be evidenced.

### COSHH

Cleaning operations involving hazardous substances should have appropriate risk assessment, controls, staff information/instruction and training. The website can make COSHH information available to commercial/care-home clients, but operational compliance must be supported by real internal procedures and records.

---

<a id="18-hosting-infrastructure-and-environments"></a>
## 18. Hosting, Infrastructure and Environments

The proposed environment should separate development from live customer data.

Recommended environments:

- **Development** — active development and integration work.
- **Staging/UAT** — client review and pre-production testing.
- **Production** — live public website.

Optional additional environment for larger Phase 2 development:

- **QA/Integration** — automated and integration testing.

Infrastructure should include:

- Managed or professionally maintained Linux hosting.
- Suitable PHP/runtime/database/search services for the chosen Magento version.
- CDN where appropriate.
- WAF/security layer.
- Transactional email service.
- Off-site backups.
- Centralised logging.
- Uptime monitoring.
- Error monitoring.
- Performance monitoring.

### Backup requirements

- Automated database backups.
- Media/file backups.
- Configuration/code stored in source control.
- Defined backup retention.
- Regular restore testing.

A backup that has never been tested for restoration should not be treated as a complete disaster-recovery strategy.

---

<a id="19-integrations"></a>
## 19. Integrations

Initial integrations are likely to include:

- Payment provider.
- Transactional email provider.
- Analytics.
- Search Console.
- Cookie/consent platform.
- Shipping/carrier provider if required.
- CAPTCHA/anti-bot service where appropriate.

Potential later integrations include:

- Accounting software.
- CRM.
- ERP.
- PIM.
- Manufacturer/supplier stock feed.
- Courier APIs.
- SMS provider.
- WhatsApp Business messaging.
- Mapping/geocoding.
- Route optimisation.
- Calendar providers.
- Workforce/HR systems.
- Review platforms.
- Marketing automation.

All third-party services should be recorded in an integration register with:

- Business owner.
- Technical owner.
- Authentication method.
- Data exchanged.
- Failure behaviour.
- Rate limits.
- Privacy implications.
- Renewal/licensing responsibility.

---

<a id="20-phase-2--cleaning-operations-platform"></a>
## 20. Phase 2 — Cleaning Operations Platform

<a id="201-phase-2-strategic-goal"></a>
### 20.1 Phase 2 Strategic Goal

Phase 2 should transform the cleaning section from a marketing/enquiry channel into an operational platform for customers, staff and administrators.

The most important architectural decision is that this functionality should be treated as a **business application**, not simply as extra website pages.

<a id="202-customer-booking-and-scheduling"></a>
### 20.2 Customer Booking and Scheduling

Potential capabilities:

- Online service selection.
- Postcode/service-area validation.
- Property/site type.
- One-off or recurring service.
- Service duration estimation.
- Preferred dates/times.
- Real-time or controlled availability.
- Add-on services.
- Quote-before-booking workflows.
- Instant booking for standardised services.
- Deposit or full-payment rules.
- Rescheduling.
- Cancellation.
- Booking history.
- Recurring booking rules.
- Blackout dates.
- Bank-holiday rules.

Not every service must use the same booking model. For example:

- Domestic standard cleans may become directly bookable.
- Commercial and care-home cleaning may remain quote/contract based.

<a id="203-cleaner-and-workforce-administration"></a>
### 20.3 Cleaner and Workforce Administration

Admin functionality may include:

- Cleaner/staff profiles.
- Employment/contractor status field.
- Contact details.
- Working regions.
- Availability.
- Working hours.
- Skills/services.
- Site authorisations.
- Training records.
- DBS status/reference metadata where lawfully stored and required.
- Compliance document expiry dates.
- Supervisor relationships.
- Leave/unavailability.
- Preferred/max weekly workload.

The system should avoid storing unnecessary sensitive data and should separate HR information from operational scheduling where appropriate.

<a id="204-operations-and-dispatch"></a>
### 20.4 Operations and Dispatch

Operations users may require:

- Day/week/month calendar views.
- Unassigned job queue.
- Drag-and-drop allocation.
- Cleaner availability indicators.
- Travel/location awareness.
- Conflict detection.
- Required-skill matching.
- Site-specific requirements.
- Preferred cleaner continuity.
- Supervisor allocation.
- Job notes.
- Priority flags.
- Reassignment.
- Absence management.
- Live job status.
- Exception queue.

Job statuses could include:

- Enquiry.
- Quoted.
- Awaiting approval.
- Confirmed.
- Assigned.
- En route.
- In progress.
- Completed.
- Quality review required.
- Cancelled.
- Invoiced.

<a id="205-customer-portal"></a>
### 20.5 Customer Portal

A Phase 2 customer portal may include:

- Account profile.
- Multiple addresses/sites.
- Upcoming bookings.
- Previous services.
- Reschedule/cancel request.
- Book again.
- Recurring schedule view.
- Service notes.
- Approved documents.
- Quotes.
- Invoices.
- Payment status.
- Messages.
- Issue reporting.
- Feedback/review submission.

Commercial/care-home accounts may require multiple user roles and multiple sites under a single organisation.

<a id="206-cleaner-portal--mobile-experience"></a>
### 20.6 Cleaner Portal / Mobile Experience

A mobile-first PWA can initially avoid the overhead of native iOS/Android apps while still providing a strong field-worker experience.

Capabilities may include:

- Secure login.
- Today’s jobs.
- Route/location link.
- Site contact details.
- Site access instructions.
- Job checklist.
- COSHH/site documents.
- Start job.
- Finish job.
- Break/issue status where required.
- Photo upload where policy permits.
- Completion notes.
- Incident/issue reporting.
- Customer signature where appropriate.
- Offline-tolerant checklist behaviour where feasible.

Location tracking should only be introduced where there is a defined operational purpose, lawful basis and clear workforce policy. Continuous surveillance should not be introduced simply because it is technically possible.

<a id="207-commercial-and-care-home-contract-management"></a>
### 20.7 Commercial and Care Home Contract Management

Commercial clients may need a hierarchy of:

- Organisation.
- Contract.
- Site/location.
- Service schedule.
- Areas/zones.
- Tasks.
- Frequencies.
- Assigned team.
- Site documents.
- Contacts.
- SLA/KPI.
- Issues.
- Quality inspections.

A care-home contract might define different schedules for bedrooms, communal areas, kitchens, bathrooms and high-touch areas.

<a id="208-documents-compliance-and-coshh"></a>
### 20.8 Documents, Compliance and COSHH

The operations platform can maintain controlled documents such as:

- COSHH data sheets.
- Risk assessments.
- Method statements.
- Site instructions.
- Cleaning checklists.
- Training material.
- Insurance documents.
- Certifications.
- Client-specific documentation.

Features should include:

- Version control where needed.
- Expiry dates.
- Document ownership.
- Visibility permissions.
- Staff acknowledgement where required.
- Audit trail for critical documents.

<a id="209-payments-invoicing-and-recurring-services"></a>
### 20.9 Payments, Invoicing and Recurring Services

Phase 2 can support different commercial models:

- Pay at booking.
- Deposit at booking and balance later.
- Stored payment token for recurring domestic services.
- Monthly invoicing for commercial clients.
- Contract billing.
- Credit terms.

Accounting integration should be considered rather than building a complete accounting system into the platform.

<a id="2010-notifications-and-communications"></a>
### 20.10 Notifications and Communications

Potential notifications:

- Booking confirmation.
- Booking reminder.
- Cleaner assigned.
- Cleaner/service delayed.
- Booking rescheduled.
- Booking completed.
- Payment receipt.
- Invoice issued.
- Quote issued.
- Compliance document approaching expiry.
- Staff shift reminder.

Channels may include:

- Email.
- SMS.
- Push notification for PWA/app.
- WhatsApp Business where appropriate and consented.

A central notification service should prevent business logic from being duplicated across channels.

<a id="2011-ai-assistants-and-automation"></a>
### 20.11 AI Assistants and Automation

AI can provide meaningful operational value in Phase 2, but it should be introduced into well-defined workflows rather than used as a generic chatbot.

#### Customer booking assistant

Potential functions:

- Ask structured questions conversationally.
- Identify domestic/commercial/care-home requirement.
- Capture postcode and service requirements.
- Recommend the correct service category.
- Gather quote information.
- Suggest available booking windows from the scheduling system.
- Answer approved FAQs.
- Escalate to a human when uncertain.

The AI should retrieve prices, availability and policies from authoritative systems rather than inventing them.

#### Operations scheduling assistant

The AI/optimisation layer can suggest cleaner assignments using factors such as:

- Availability.
- Skills.
- Location/travel time.
- Contract/site eligibility.
- Preferred cleaner continuity.
- Existing workload.
- Shift length.
- Supervisor requirements.

Recommended design principle:

> AI proposes; authorised operations staff approve where the decision affects staffing, customer commitments or exceptions.

#### Operations exception assistant

Potential triggers:

- Cleaner unavailable.
- Double booking.
- Booking at risk because of travel time.
- Compliance document expired.
- Unassigned job approaching start time.
- Recurring service has no valid staff allocation.

The assistant can present the issue and suggested resolution.

#### Customer service assistant

With appropriate authentication, it may help staff answer:

- What bookings does this customer have?
- Was the last job completed?
- Is an invoice outstanding?
- What did the customer report previously?

#### Knowledge assistant

An internal AI assistant can answer staff questions using controlled company material such as:

- Service procedures.
- COSHH documents.
- Site instructions.
- FAQs.
- Product documentation.

Permissions must be respected so the assistant cannot reveal documents that the user would not otherwise be allowed to access.

#### AI governance requirements

- Clear identification when users are interacting with AI where appropriate.
- Human escalation.
- Approved knowledge sources.
- Audit/logging appropriate to the workflow.
- No autonomous creation of fabricated policies, prices or availability.
- Data-minimisation controls.
- Vendor/data-processing review.
- Testing for unsafe or discriminatory allocation patterns.

<a id="2012-reporting-and-management-dashboards"></a>
### 20.12 Reporting and Management Dashboards

Potential Phase 2 dashboards include:

- Enquiries by source.
- Quote conversion rate.
- Bookings by service.
- Revenue by service category.
- Recurring vs one-off services.
- Cleaner utilisation.
- Unassigned work.
- Cancellations.
- Customer retention.
- Repeat booking rate.
- Service completion rate.
- Complaints/issues.
- Commercial contract KPIs.
- Expiring compliance documents.

Reporting should begin with business decisions and KPIs rather than attempting to capture every possible metric.

---

<a id="21-phase-3-and-longer-term-opportunities"></a>
## 21. Phase 3 and Longer-Term Opportunities

Potential future capabilities include:

### Ecommerce

- Manufacturer stock integration.
- Automated supplier lead times.
- ERP integration.
- PIM integration.
- Trade accounts.
- Account-specific pricing.
- Volume pricing.
- Purchase-order payment for approved customers.
- Quote-to-order workflow.
- B2B portal.
- Multi-warehouse stock.
- Marketplace feeds.
- Advanced product configurator.
- Product bundles.
- Installer/partner network.

### Cleaning services

- Route optimisation.
- Automated rota generation.
- Capacity forecasting.
- Quality audit scoring.
- Service-level dashboards for enterprise customers.
- Multi-branch operating model.
- Franchise capability if ever required.
- Automated renewal workflows.
- Customer churn prediction.
- Predictive staffing requirements.

### Cross-business opportunities

- Recommend accessibility products to relevant care/commercial customers.
- Product installation services if introduced.
- Bundled product/service offers.
- Care-sector account relationships spanning products and cleaning services.
- Unified CRM view of customer relationships.

---

<a id="22-data-model-and-integration-readiness"></a>
## 22. Data Model and Integration Readiness

Good data design in Phase 1 will reduce Phase 2 rework.

### Core entities likely to exist across the programme

- Customer.
- Organisation.
- Site/address.
- Contact.
- Product.
- Product variant.
- Order.
- Enquiry.
- Quote.
- Service.
- Booking/job.
- Recurring schedule.
- Cleaner/staff member.
- Availability.
- Assignment.
- Document.
- Invoice.
- Payment.
- Communication.
- Issue/incident.

Each system should own its authoritative data. For example:

- Magento should remain authoritative for ecommerce product/order data.
- The service platform should remain authoritative for cleaning bookings and workforce allocation.
- An accounting platform may become authoritative for posted accounting transactions.

---

<a id="23-administration-and-user-roles"></a>
## 23. Administration and User Roles

Role-based access should be designed rather than giving every administrator full access.

Potential roles include:

### Phase 1

- Super administrator.
- Ecommerce manager.
- Order/customer service user.
- Product/catalogue editor.
- Marketing/content editor.
- SEO/content contributor.

### Phase 2

- Operations administrator.
- Scheduler/dispatcher.
- Service manager.
- Cleaner.
- Supervisor.
- Finance user.
- Commercial account manager.
- Compliance manager.
- Read-only management/reporting user.

Sensitive data and actions should be limited by role.

---

<a id="24-testing-and-quality-assurance"></a>
## 24. Testing and Quality Assurance

Testing should be planned throughout development.

### Functional testing

- Product configuration.
- Pricing.
- Stock state.
- Basket.
- Checkout.
- Payment success and failure.
- Promotions.
- Shipping rules.
- Account creation/login.
- Password reset.
- Order email.
- Enquiry forms.
- File uploads.
- Search.
- Filters.

### Responsive/browser testing

Test current supported versions of major browsers and representative devices, including:

- Chrome.
- Edge.
- Firefox.
- Safari.
- iOS Safari.
- Android Chrome.

### Accessibility testing

- Automated scans.
- Keyboard-only journeys.
- Screen reader spot checks.
- Form errors.
- Focus management.
- Zoom/text scaling.

### Performance testing

- Homepage.
- Category page.
- Product page.
- Search.
- Basket.
- Checkout.
- Service landing pages.

### Security testing

- Dependency scan.
- Configuration review.
- Authentication/authorisation checks.
- Form/input testing.
- Upload testing.
- Admin exposure review.
- Payment/webhook review.

### UAT

The client should complete structured user-acceptance testing against agreed acceptance criteria before production launch.

---

<a id="25-deployment-and-launch-plan"></a>
## 25. Deployment and Launch Plan

The launch should use a documented checklist.

### Pre-launch

- Production infrastructure ready.
- DNS plan approved.
- SSL certificate active.
- Payment provider production credentials.
- Shipping configuration verified.
- Transactional email domain authenticated.
- Legal pages approved.
- Product data approved.
- Stock/lead-time values verified.
- Analytics configured.
- Consent management configured.
- Search engine indexation settings checked.
- Redirects loaded where replacing an existing site.
- Backups verified.
- Monitoring enabled.
- UAT signed off.

### Launch

- Final database/content deployment.
- DNS cutover if required.
- Cache/CDN refresh.
- Smoke testing.
- Live payment test.
- Live enquiry test.
- Transactional email test.
- Search/indexation verification.

### Post-launch

- Error monitoring.
- Checkout monitoring.
- Analytics validation.
- Search Console checks.
- Performance checks.
- Review initial customer behaviour.
- Prioritise rapid fixes where genuine launch issues are discovered.

---

<a id="26-training-and-handover"></a>
## 26. Training and Handover

Training should be role-specific.

### Ecommerce/content training

- Login and security.
- Product creation.
- Configurable products.
- Images.
- Categories.
- Stock/lead times.
- Orders.
- Refund workflow.
- Promotions.
- CMS pages.
- Blog/resources.
- Media management.
- SEO fields.

### Phase 2 training

- Customer records.
- Bookings.
- Recurring schedules.
- Cleaner availability.
- Allocation.
- Exceptions.
- Reporting.
- Document management.
- AI-assistant approvals/escalation.

### Handover deliverables

- Admin guide.
- Architecture overview.
- Integration register.
- Environment details.
- Deployment process.
- Backup/recovery procedure.
- Support escalation route.

Secrets and credentials should be transferred securely and should not be embedded in general documentation.

---

<a id="27-support-maintenance-and-continuous-improvement"></a>
## 27. Support, Maintenance and Continuous Improvement

A modern ecommerce site requires ongoing maintenance.

Recommended ongoing activities:

- Security updates.
- Platform updates.
- Extension updates.
- Dependency monitoring.
- Backup checks.
- Uptime monitoring.
- Error monitoring.
- Performance optimisation.
- SEO review.
- Analytics review.
- Conversion optimisation.
- Broken-link checks.
- Content updates.
- Accessibility regression checks.

A release process should distinguish urgent security/hotfix work from planned feature releases.

---

<a id="28-estimated-timescales"></a>
## 28. Estimated Timescales

Timescales below are indicative and assume the client supplies required content, product data, images, credentials and approvals without material delay.

### Phase 0 — Discovery and solution definition

**Estimated duration: 1–2 weeks**

Activities:

- Workshops.
- Architecture.
- Sitemap.
- Data model.
- UX flows.
- Integration decisions.
- Project backlog.

### Phase 1 — Design, ecommerce and services website

**Estimated duration: 8–12 weeks after Phase 0**

A typical sequence:

| Workstream | Indicative Duration | Notes |
|---|---:|---|
| UX / visual design | 2–3 weeks | Can overlap technical setup |
| Magento environment and technical foundation | 1–2 weeks | Begins early |
| Theme/frontend build | 3–5 weeks | Iterative |
| Ecommerce configuration | 3–5 weeks | Overlaps frontend work |
| CMS/service pages | 2–4 weeks | Depends on content readiness |
| Payment/shipping/integrations | 1–3 weeks | Depends on providers |
| Product import/configuration | 1–2 weeks | Assumes clean data |
| QA, accessibility, performance | 1–2 weeks | Continuous plus final pass |
| Client UAT and launch | 1–2 weeks | Depends on review turnaround |

Several workstreams run in parallel, so the durations should not be added together linearly.

### Phase 1.5 — Post-launch optimisation and catalogue expansion

**Estimated duration: 2–6 weeks of iterative work**

Possible work:

- Anti-ligature grab rails.
- Colostomy shelves.
- Additional filters.
- Search tuning.
- CRO changes.
- SEO content expansion.
- Additional automation.

### Phase 2 — Cleaning operations platform

Phase 2 should be split into releases rather than held back for one large launch.

#### Phase 2A — Core operations MVP

**Estimated duration: 8–12 weeks**

- Customer/service records.
- Booking calendar.
- Cleaner profiles.
- Availability.
- Assignment.
- Basic recurring schedules.
- Operations dashboard.
- Notifications.

#### Phase 2B — Customer and cleaner portals

**Estimated duration: 6–10 weeks**

- Customer portal.
- Cleaner PWA/mobile views.
- Job checklist/completion.
- Documents.
- Commercial site structure.
- Payments/invoices integration.

#### Phase 2C — AI and advanced automation

**Estimated duration: 4–8 weeks for first production workflows**

- Booking assistant.
- Scheduling suggestions.
- Exception assistant.
- Knowledge assistant.
- Human approval/audit controls.

These phases can overlap. A realistic end-to-end Phase 2 programme is approximately **16–24 weeks**, depending on final operational complexity and integration requirements.

### Overall programme view

A practical objective is:

- **Phase 1 revenue-generating website live approximately 9–14 weeks from project start**, including discovery.
- Operational Phase 2 delivered incrementally after launch, rather than delaying the website while all service-management functionality is built.

---

<a id="29-project-deliverables"></a>
## 29. Project Deliverables

### Phase 0

- Discovery documentation.
- Approved sitemap.
- User journeys.
- Wireframes.
- Technical architecture.
- Product data specification.
- Integration register.
- Delivery backlog.

### Phase 1

- Responsive Shi3ld Alliance website.
- Magento Open Source implementation.
- Custom theme/design system.
- Product catalogue.
- Configurable product setup.
- Categories/filters/search.
- Basket and checkout.
- Payment integration.
- Shipping configuration.
- Customer accounts.
- Cleaning service pages.
- Domestic/commercial/care-home sections.
- Quote/enquiry forms.
- Gallery/reviews components.
- CMS/editorial components.
- Analytics implementation.
- Consent/cookie implementation.
- Technical SEO foundation.
- Accessibility testing.
- Performance testing.
- UAT support.
- Production deployment.
- Admin training.
- Documentation.

### Phase 2

Deliverables will be broken down by release but may include:

- Service operations API/application.
- Operations admin portal.
- Booking calendar.
- Cleaner management.
- Customer portal.
- Cleaner mobile/PWA interface.
- Notification engine.
- Document/compliance module.
- Reporting dashboards.
- AI assistants.
- Integration services.

---

<a id="30-client-inputs-and-responsibilities"></a>
## 30. Client Inputs and Responsibilities

Timely client input is critical to the proposed timescales.

Shi3ld Alliance should provide or approve:

- Logo/brand assets.
- Brand guidelines if available.
- Company details.
- Contact details.
- Product list.
- SKUs.
- Product variants.
- Prices.
- VAT status/classifications.
- Product photography.
- Dimensions/specifications.
- Installation documents.
- Manufacturer documentation.
- Stock information.
- Lead-time rules.
- Shipping rules.
- Returns policy.
- Warranty information.
- Cleaning service descriptions.
- Service area.
- Cleaning photography.
- Staff/trust/compliance statements.
- Evidence supporting DBS/COSHH/accreditation claims.
- Testimonials and permission to publish them.
- Legal policy text or approval of drafted text.
- Payment-provider account.
- Shipping/carrier account where integrated.
- Analytics/Search Console access where existing.
- Domain/DNS access.
- Named project approver.

For Phase 2, the client will also need to define real operational rules rather than expecting software to infer them, including scheduling priorities, cancellation rules, cleaner availability, geographic areas and escalation processes.

---

<a id="31-assumptions"></a>
## 31. Assumptions

This proposal assumes:

- The initial ecommerce range is approximately ten products plus variants.
- Anti-ligature grab rails will be added within approximately three months of launch.
- Two colostomy shelf types will be introduced, each initially in white and grey where applicable.
- The initial cleaning website is enquiry/quotation-led rather than fully transactional.
- UK is the primary initial market unless confirmed otherwise.
- English is the initial website language.
- Product and service content can be supplied or approved by the client.
- A reputable third-party payment gateway will be used.
- Phase 2 requirements will be refined through workflow discovery before development.
- Native mobile applications are not required for the first Phase 2 release; a responsive/PWA approach is preferred initially.

---

<a id="32-out-of-scope--separately-scopable-items"></a>
## 32. Out of Scope / Separately Scopable Items

Unless explicitly added to the project backlog, the following should be treated as separate scope:

- Full ERP implementation.
- Full CRM implementation.
- Bespoke accounting system.
- Native iOS/Android applications.
- Warehouse-management system.
- Hardware procurement.
- Point-of-sale system.
- Full manufacturer ERP integration where no API specification exists.
- Professional product photography.
- Full brand identity creation if branding is not yet defined.
- Legal advice/legal drafting by a solicitor.
- Paid advertising management.
- Ongoing SEO retainers/content campaigns.
- Translation/localisation into additional languages.
- Data cleansing of large legacy datasets not supplied in an agreed format.
- Advanced route optimisation before Phase 2 operational data exists.

These items can be added later without changing the overall architecture.

---

<a id="33-risks-and-mitigations"></a>
## 33. Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Product data arrives incomplete | Delays ecommerce build and creates poor product pages | Agree mandatory product-data template during Phase 0 |
| Shipping rules are unclear | Checkout inaccuracies | Confirm product dimensions, service regions and carrier rules before build completion |
| Magento is treated as the Phase 2 workforce system | Technical debt and difficult maintenance | Keep operations in a separate application layer |
| Too many extensions/plugins are installed | Upgrade/security complexity | Prefer core functionality and carefully reviewed extensions |
| Content is supplied late | Launch delay | Create content matrix and approval deadlines early |
| Care-home compliance claims are not evidenced | Legal/reputational risk | Require client sign-off/evidence for claims |
| AI is introduced before workflows are defined | Unreliable automation | Build deterministic workflow first, then layer AI assistance onto it |
| Staff allocation AI makes unchecked decisions | Operational/HR risk | Human approval, transparent rules and auditability |
| Phase 1 is overloaded with Phase 2 features | Revenue launch delayed | Protect Phase 1 MVP scope |
| Platform security updates are neglected | Security exposure | Formal maintenance/patch process |
| Accessibility is tested only at the end | Expensive rework | Include accessibility in design/components and continuous QA |
| Customer data is duplicated across systems without ownership rules | Data inconsistency | Define authoritative system per entity and integrate via APIs |

---

<a id="34-acceptance-criteria"></a>
## 34. Acceptance Criteria

Phase 1 should not be considered complete solely because pages are visible. Acceptance should be against agreed functional criteria.

### Ecommerce acceptance examples

- All approved launch products are present.
- Product variants select correctly.
- White, blue and dark-grey grab-rail variations operate as configured.
- Correct price/SKU/stock/lead-time data is shown.
- Categories and filters work as agreed.
- Search returns relevant products.
- Basket totals are correct.
- Shipping charges are correct for test scenarios.
- Live payment can be completed successfully.
- Failed payments do not create an incorrect paid state.
- Customer confirmation emails are delivered.
- Admin can see and manage orders.
- Customer can view order history.

### Services acceptance examples

- Domestic, commercial and care-home sections are live.
- Required service pages are editable through CMS.
- Quote forms show the correct fields by service type.
- Enquiries reach the correct internal destination.
- Customer acknowledgement is sent.
- Form data is stored/handled according to agreed privacy design.

### Quality acceptance examples

- No critical accessibility defects on core journeys.
- No critical security issues identified in agreed testing.
- Supported mobile and desktop browsers pass core journey tests.
- 404/500 handling is branded and functional.
- Analytics records agreed conversion events.
- Cookie controls behave as configured.
- Production backups and monitoring are active.
- Admin training and handover documentation are complete.

---

<a id="35-recommended-next-steps"></a>
## 35. Recommended Next Steps

The recommended sequence is:

1. Approve the high-level architectural direction.
2. Confirm whether Magento Open Source is the preferred ecommerce platform or whether Magento and Shopware should be compared in a final technical selection exercise.
3. Run Phase 0 discovery workshops.
4. Create the product data spreadsheet/schema and collect all launch product information.
5. Confirm shipping and payment providers.
6. Confirm service locations and cleaning quotation rules.
7. Produce sitemap, wireframes and design concepts.
8. Set up development/staging infrastructure and source control.
9. Begin Magento configuration and theme development while content is prepared.
10. Build Phase 1 ecommerce and service pages in parallel.
11. Complete QA, accessibility, performance and UAT.
12. Launch Phase 1.
13. Use real enquiries and operational learning to finalise Phase 2 workflows before building the service-management application.

---

<a id="36-conclusion"></a>
## 36. Conclusion

Shi3ld Alliance has two business models that require different digital capabilities: a product-led ecommerce operation and a people-led service operation. The strongest long-term solution is therefore not to search for one platform that performs every possible function, but to establish a stable commerce/content foundation and connect it to specialist operational functionality as the cleaning business develops.

Magento Open Source is recommended for Phase 1 because the planned accessibility catalogue benefits from its mature configurable-product, inventory, attribute and ecommerce capabilities, while its CMS/Page Builder functionality is sufficient for the initial domestic, commercial and care-home service marketing areas.

For Phase 2, cleaner scheduling, recurring bookings, customer portals, compliance, operations dashboards and AI-assisted workforce organisation should be developed as a dedicated service-management application, preferably using Laravel or an equivalent modern application framework. This gives Shi3ld Alliance flexibility to create workflows that match the business rather than being constrained by generic ecommerce or CMS plugins.

The phased approach allows the business to begin selling products and generating cleaning enquiries quickly, while preserving a clear route to a much more capable digital operating platform.

The most important next step is a short discovery phase that validates product data, operational processes, shipping, service areas and the final platform selection before build work begins.

---

## Reference Notes

This proposal was prepared using current platform and regulatory guidance available in September 2026, including official documentation relating to Magento Open Source/Adobe Commerce configurable products, CMS/Page Builder and APIs; WooCommerce variable products and bookings; BigCommerce variants; Shopware product variants and Shopping Experiences; W3C WCAG 2.2; UK ICO privacy/cookie guidance; GOV.UK online and distance-selling guidance; Disclosure and Barring Service guidance; and HSE COSHH guidance.

The proposal is a project/business scope and technical recommendation. It should not be treated as legal, employment, health-and-safety or regulatory advice. Relevant professional advice should be obtained where required.
