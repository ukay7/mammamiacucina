# MMC — Project and Conversation Handover
Last updated: 29 September 2026
Project: Mamma Mia Cucina (Canadian bakery / food catalogue, online ordering and administration)
Working directory: C:\xampp\htdocs\Ramnode\MMC
Application directory: C:\xampp\htdocs\Ramnode\MMC\website

This is a curated handover of the project, the conversation, implemented work and current decisions. It is NOT a verbatim chat transcript. Read it alongside the actual repository. The user's latest instructions override older decisions summarized here.

## 1. Start here — critical current state

- Continue the existing Laravel application; do not rebuild it.
- MANY local changes and new files are not committed. Preserve them.
- Latest observed branch: main. Latest observed commit: b3c766e, "Use QR field for barcode labels and compact product actions".
- Recent payment, content-management, catalogue-reader, Product DNA, pricing and column-selector work is LOCAL. Do not claim it is deployed or pushed.
- User plans deployment after checking several fixes. No deployment was performed in the latest work.
- Most important constraint: NEVER delete or replace existing live products, categories, banners, orders, inventory or uploads.
- No migrate:fresh, db:wipe, blanket seeding, production database replacement, hard resets or clean commands.
- Do not rotate APP_KEY: gateway credentials are encrypted with it.
- Do not copy the local database over production.
- No passwords, real gateway keys or login credentials are included here.
- The latest UI request is completed: Choose columns on the Products page is CLOSED by default; clicking the button opens it.
- No further development request is pending at handover. The user is moving to another chat and will continue checking.

## 2. Current decisions — DO NOT revive superseded features

The user originally requested a separate New Pricing tab, pack variants, drafts and price approvals. Those were implemented, then explicitly rejected by the user.

CURRENT behavior:
- Editor has FIVE visible tabs:
  1. Identity
  2. Packing and handling
  3. Images and ingredients
  4. Pricing
  5. Notes and sources
- New Pricing tab and configurable selling-pack rows are removed from the UI.
- Extracted source data tab is hidden. Stored source data is preserved.
- No approval screen, approval permission or submit/approve routes in the active application.
- Calculate Preview performs calculations without changing the saved price.
- In Pricing, choose the per-piece OR per-carton amount and click Save Price to publish directly.
- Identity also has an editable Total Selling Price (CAD) beside the product name. Save Product saves that manually entered amount.
- The calculator is OPTIONAL. Existing prices must remain editable without entering supplier-cost inputs.
- Both approaches update the SAME products.total_selling_price_cad field.
- After calculator Save Price, JavaScript updates the Identity price field, displayed price and editor_revision. Do not regress this, or later Save Product can restore an old value.
- Old draft/settings and review/audit tables are retained for compatibility and data preservation. Their names do NOT mean approvals are still active.
- ProductPriceWorkflow now exposes savePrice(), not the former submit/decide flow.
- Direct calculator saves append a snapshot with status "saved"; old pending proposals are superseded when saving.
- Old review Blade files may still exist, but are unlinked and their routes are retired. Do not reconnect them accidentally.
- Customer/POS selectable pack variants were never enabled. One existing store unit still means one existing inventory/order unit. Saving a carton price does NOT convert stock quantities.

## 3. User preferences and collaboration

- User writes English, Hindi and Roman Urdu/Punjabi. Most recently asked for English explanations.
- Wants direct, practical changes and quick checking URLs, not repeated permission questions.
- Preserve existing functionality and data while changing layout.
- Match the existing cream, burgundy/red, gold and dark-brown Mamma Mia theme.
- Admin must support fast work: modal product editing, top Save next to Close, visible price, compact sidebar.
- Mobile responsiveness matters.
- User explicitly wanted discussion only earlier, then explicitly authorized coding. Current implementation work is authorized when requested; no need to repeat the old discussion-only restriction.
- Do not infer a new request to deploy from old historical "push live" messages.
- Do not create a new chat or send other chats messages unless asked. This request only asked for a handover file.
- Keep progress updates concise and report test/deployment limitations honestly.

## 4. Stack and local operation

- Laravel 12, Blade templates, PHP >=8.2 declared in composer.json.
- Working bundled runtime: project-root .tools/php/php.exe (PHP 8.4); use this instead of the old XAMPP PHP.
- OpenSpout ^4.28 for XLSX imports/exports.
- PHPUnit 11, Laravel Pint.
- App code is under website/, not the original theme directory.
- Original theme reference: theme_vanila/bakery.
- Admin theme assets: website/public/admin-assets, including SmartAdmin assets.
- Local browser origin used throughout: http://127.0.0.1:18088
- Admin login: http://127.0.0.1:18088/admin/login
- Products: http://127.0.0.1:18088/admin/products
- Catalogue reader: http://127.0.0.1:18088/catalogue
- Contact: http://127.0.0.1:18088/contact
- Public dynamic product details use /products/{slug}; product listing is /product-grid.
- Check whether the local server is actually running before promising the URL works. It may stop between sessions.

Safe foreground local startup in PowerShell, if the port is free:

    Set-Location 'C:\xampp\htdocs\Ramnode\MMC\website\public'
    & 'C:\xampp\htdocs\Ramnode\MMC\.tools\php\php.exe' -S 127.0.0.1:18088 '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'

The working directory MUST be public. Earlier launching the Laravel router from website/ caused a missing website/index.php fatal error.
website/start-local.ps1 follows the public-directory pattern but uses OLD port 8088. Account for that difference.
For a persistent background process use Start-Process with WindowStyle Hidden, explicit working directory and logs; check the port/process first and avoid duplicate servers.

Run artisan/tests from website:

    Set-Location 'C:\xampp\htdocs\Ramnode\MMC\website'
    & '..\.tools\php\php.exe' artisan test
    & '..\.tools\php\php.exe' artisan view:clear
    & '..\.tools\php\php.exe' artisan migrate --force

Tests use SQLite :memory:, array sessions/cache/mail and synchronous queue, as configured in phpunit.xml. Do not change tests to point at the real database.

Environment notes:
- Shell is PowerShell.
- Node: C:\Program Files\nodejs\node.exe
- In this session the default exec sandbox often returned helper_unknown_error; approved require_escalated calls worked. This is an execution-environment issue, not application behavior.
- Git may require: git -c safe.directory=C:/xampp/htdocs/Ramnode/MMC ...
- .tools contains ignored scratch scripts/screenshots/logs, not production application code.
- No AGENTS.md was found in earlier checks; re-check if workspace instructions change.
- Prefer rg for file searches; avoid printing .env or credential records.
- Some PHP image-processing features are limited locally (GD was unavailable in earlier checks).

## 5. Website and admin modules

### Products, categories and allergies
- Product records retain the original workbook fields, codes and up to eight decimal places.
- Product categories are many-to-many, with category_id retained as a compatible primary category.
- Preserve category/allergy assignments during editing/import.
- Allergies have CRUD and name/icon support; source icons were in public/media/icons.
- Add/Edit uses multi-checkbox categories and allergies.
- Product grid has category, allergy and price filtering.
- Public product detail shows assigned allergy names/icons.
- Product-detail magnifier/zoom was removed at the user's request.
- Product cards and filter sidebar were made more compact.
- Website product-menu dropdown was adjusted for many categories.
- View/Edit/barcode are icon actions. Preserve these and bulk category/label actions.
- Product images/videos use ProductMediaManager and private local storage with authorized admin media routes.
- Ingredient/supplier documents added separately; they are private admin downloads, not storefront media.
- Document path: product-documents/{product id} on Laravel local disk.
- Product media path: product-media/{product id} on local disk.
- Ingredient document types include PDF, Office files and standard images; up to 10 files / 20 MB each per save.
- Original image/video manager supports up to 10 files / 50 MB each per save; inspect its current validation for exact formats.

### Inventory
- Invoice quantity is not live inventory.
- Opening stock can be Not set/untracked.
- Set/add/remove adjustments are audited and reject stale or negative results.
- Adding 10 to stock of 10 yields 20; setting stock to 10 replaces the balance with 10.
- Orders deduct tracked stock once. Eligible cancellations restore it once.
- Returns/refunds are not universally automatic restocking: delivered/dispatched goods need appropriate physical-return handling.
- Do not invent stock for untracked products.
- No automatic carton/piece conversion.

### Orders and Quick Sale / POS
- Website and POS use the SAME Orders module/tables, with source/fulfillment/staff attributes.
- Quick Sale is directly below Dashboard in the sidebar and has a dashboard shortcut.
- Scanner/camera lookup can add products, increment quantities, remove rows, review totals and place orders.
- Camera scanning needs HTTPS or localhost and browser permission.
- Barcode labels are Code128 and encode ONLY the product QR Code field, preserving leading zeros. No MMC prefix or internal database ID.
- Printing/PDF labels use the browser print workflow.
- POS can also match supplier codes, with ambiguity handling.
- POS "Card" means payment taken through an external physical terminal. It does NOT initiate a hosted Stripe charge.
- Totals, prices, stock and signed sale references are checked at completion; duplicate retries should not create duplicate orders.
- See website/POS.md and app/Services/PosSale.php.

### Payments and gateway settings
- Website checkout supports cash, hosted Stripe Checkout and hosted PayPal approval.
- No card number, expiry, CVC or reusable card authorization is stored by the app.
- Server-calculated CAD totals use Total Selling Price, delivery and tax settings.
- Redirects alone cannot mark orders paid; server/provider validation is required.
- Gateway Settings has enable/disable controls, separate UAT/sandbox and production credentials, environment selection and credential-authentication checks.
- Secrets are encrypted with APP_KEY and not returned to browser forms; blank fields preserve them.
- Both provider environments need their own credentials/webhooks.
- Payment reports cover cash/card/PayPal, status, date and source filters, with sandbox payments excluded by default.
- Scheduler reconciliation is required for abandoned payments and correct stock release.
- Automated provider tests MOCK API responses. Actual Stripe and PayPal sandbox transaction/webhook tests remain dependent on credentials and reachable callbacks; do not claim they happened merely because PHPUnit passed.
- See website/PAYMENTS.md for precise setup, callbacks, scheduler, reports and refund limits.

### Home, About, Contact, Gallery, Catalogue
- Home has dynamic banners, categories/collections and other settings; preserve existing live content.
- About Us is dynamic: heading, description, image, button text/destination and a list of highlights.
- Three highlights fit normally; additional items scroll horizontally. Mobile behavior was checked.
- About migration initializes the existing default content; local later edits/uploads are NOT automatically synchronized by Git.
- Contact Us is dynamic, including page content/image, opening hours and WhatsApp, Instagram, Facebook, website URL, email and phone.
- Contact information is shared with footer/site contact areas.
- Contact enquiries are stored and managed separately.
- Contact image was reduced to a compact height (approximately 240px desktop / 190px mobile in the current CSS).
- Gallery Events has its own CRUD and photos.
- Catalogue link sits between Products and Gallery in website navigation.
- Catalogue CRUD manages uploaded page images, order, publication and optional existing category association.
- Multiple catalogue pages may use the same product category.
- Cover/About/Contact pages can use catalogue-only labels without adding/changing product categories.
- Catalogue reader supports categories, all-page thumbnails, page navigation, progress/page control, zoom and fullscreen.
- Initial 36 sample pages and thumbnails are bundled in public/assets/catalogue with database/data/catalogue-pages.json.
- Reader performance uses static optimized images/thumbs for bundled content, nearby-page preloading and lazy thumbnails with feedback/retry.
- Uploaded pages use revision-aware caching and controlled routes.
- Reader frame height was increased slightly (approximately 88dvh desktop / 78dvh mobile).
- See website/CATALOGUE.md.

## 6. Compact sidebar — preserve ordering

- Dashboard
- Quick Sale / POS
- Manage Product:
  - View Products
  - Category Management
  - Inventory
  - Bulk Product Uploader
  - Allergies
- Manage Order:
  - View Orders
  - Payment Report
- Contact Us Queries
- Settings, in website-content order:
  - Home
  - About Us
  - Catalogue
  - Gallery Events
  - Contact Us
  - General Settings
  - User Management (View Users; User Types & Permissions)
  - Gateway Settings
- Social Links: configured Instagram, Facebook, website destinations.

User Management was explicitly moved just ABOVE Gateway Settings.
There should be NO Price Approvals link now.
Permissions still govern visibility AND server access. Super Admin bypasses through its permission set.

Relevant views:
- resources/views/admin/layout.blade.php
- resources/views/admin/sidebar-groups.blade.php
- resources/views/admin/sidebar-users.blade.php
- resources/views/admin/sidebar-social.blade.php

## 7. Product DNA reference, metadata and formulas

Reference supplied by client:
C:\xampp\htdocs\Ramnode\MMC\website\public\sample\MAMMA_MIA_Product_DNA_OWNER (4).html

This is a large standalone HTML reference with embedded data/images and client formulas. Earlier analysis found 78 records (66 active/12 inactive). Do not bulk-import these over existing live products without explicit instruction. It is a reference, not the production app.

The other reference is a Mamma_Mia_Cucina_Flipbook_Email (1) sample folder under public/sample.
Development samples are not required for production; inspect public/sample before deployment because source documents may contain supplier information.

Field configuration:
- config/product_fields.php: original product fields and labels.
- config/product_dna.php: additional grouped metadata stored in products.dna JSON.
- Original fields include supplier, product_code, qr_code, premium_marketing_name, italian_subtitle, original_description, status, uom, invoice_quantity, cartons, pieces_per_pack, unit_weight_g, pack_weight_kg, size_diameter_cm, supplier_unit_price_eur, supplier_discount, supplier_unit_price_eur_2, supplier_line_total_eur, rate_exchange_cad, shipping_cost_cad, surcharge_increase_cad, total_selling_price_cad, product_notes.
- qr_code corresponds to client's internal code, e.g. 100020.
- product_code is the supplier SKU, e.g. 0010821. Keep leading zeros.
- Additional fields cover manufacturer, purchaser/delivery, brand allocation, family, gluten status, supplier confirmation; carton quantities/verification, weight evidence, minimum quantities, origin/GTIN/dimensions, storage/shelf-life/preparation; ingredient/image references and verification; private owner/source notes.
- Supplier verification flags are staff-entered metadata, not a newly implemented supplier portal.
- Planning pack/minimum fields do not change checkout quantities.

Pricing algorithm in app/Services/ProductPricing.php:
1. Normalize quote to one piece:
   - piece: divisor 1
   - carton: divisor pieces_per_carton
   - supplier pack: divisor units_per_pack
   - kg: divisor 1000 / grams_per_piece
2. If quote currency is CAD, divide by fx to obtain EUR equivalent.
3. Apply supplier discounts sequentially: multiply each (1 - discount/100).
4. Convert discounted EUR per piece to CAD using fx = CAD per 1 EUR.
5. Freight per piece = freight CAD per carton / pieces per carton.
6. Landed cost = CAD purchase cost + freight per piece + other cost per piece.
7. Markup: landed * (1 + percentage/100).
8. Margin: landed / (1 - percentage/100), percentage below 100.
9. Round per-piece selling price to cents, THEN multiply for carton total.
10. Reject missing/invalid inputs, conflicting carton data and unreasonable/overflow totals.

Use Original Source Discount is only a convenience button copying source_discount into discount. Example 35+3 means 36.95% effective, not 38%.
Historical rate_exchange_cad contains a converted amount in existing records, NOT necessarily a usable FX multiplier. Do not blindly use it as fx.
There is no automatic exchange-rate feed.

Verified test example:
- EUR 2/piece, 38% discount, FX 1.65, freight CAD 5/carton, 6 pieces/carton, other cost 0, markup 50%.
- Net EUR 1.24, purchase CAD 2.046, freight/piece 0.833333..., landed CAD 2.879333...
- Selling price/piece CAD 4.32, carton CAD 25.92.
- A current product price such as 10.569 or 29.50 remains unchanged until explicitly saved; do not auto-recalculate all products.

Key implementation files:
- app/Http/Controllers/Admin/ProductController.php
- app/Http/Controllers/Admin/ProductPricingController.php
- app/Services/ProductPricing.php
- app/Services/ProductPriceWorkflow.php
- app/Services/ProductDna.php
- app/Services/CatalogueImportPlan.php
- app/Services/CatalogueImporter.php
- app/Models/Product.php, ProductPricingDraft.php, ProductPriceReview.php, ProductDocument.php
- resources/views/admin/products/form.blade.php, pricing.blade.php, dna-fields.blade.php
- public/admin-assets/product-dna.js, product-dna.css
- routes/admin.php
- config/admin.php
- website/PRODUCT_PRICING.md

Active pricing POST endpoints under products.manage:
- /admin/products/{product}/pricing/quote
- /admin/products/{product}/pricing/save
No active /pricing/submit or /admin/price-reviews endpoints.

Concurrency/preservation:
- Product editor carries editor_revision.
- Calculator save validates both pricing revision and editor revision under database locks.
- Calculator output is recomputed on server; client supplied totals are not trusted.
- Manual price changes work even on products that previously used the calculator.
- Imports cannot overwrite managed calculated prices; they direct staff to the price field/calculator.
- General edits merge metadata, preserving hidden source fields.
- Existing order price snapshots remain unchanged.

## 8. Products table — latest work

The user pointed out the reference's Choose columns panel was missing. It is now implemented.

- 39 selectable columns covering identity, packing, supplier pricing, calculated costs/prices, published price, images/ingredients and notes.
- Presets: Identity, Pricing, Missing data, Name and price, All columns, Clear selection.
- Missing data is a COLUMN preset, not a row filter.
- Current selling price is separate from calculated per-piece/per-carton values.
- Calculated columns use SAVED calculator inputs; unavailable/incomplete calculations show a dash.
- Choosing columns does not edit product data.
- Current table data is read-only; use the retained View/Edit icons for editing. Inline editable price cells from the reference were not implemented in the column-selector task.
- Actions/barcode/bulk-selection controls remain available regardless of selected data columns.
- Table scrolls horizontally and vertically (max-height 65vh); headers/actions remain accessible.
- Column selections persist per admin in localStorage and in URL/filter/pagination links.
- Excel/PDF/Print export only selected columns across ALL matching products, not just the visible page.
- Empty column selection disables exports; server rejects empty exports and unknown column keys.
- XLSX uses literal string cells, preserving leading zeros and avoiding formula interpretation.
- Excel image column exports private image URLs, not embedded images.
- PDF is browser Print → Save as PDF, not a server-rendered PDF file.
- Latest user request: selector CLOSED by default. Implemented with hidden on product-column-picker and aria-expanded=false on toggle.
- Column choices persist, but the panel starts collapsed on page load.

Files:
- app/Services/ProductColumns.php
- resources/views/admin/products/index.blade.php
- resources/views/admin/products/columns.blade.php
- resources/views/admin/products/print.blade.php
- public/admin-assets/product-columns.js
- ProductController index/productQuery/export methods
- tests/Feature/ProductColumnsTest.php

## 9. Testing evidence and limitations

Recorded results, in chronological order:
- Before direct-save simplification: full suite 136 tests / 1437 assertions passed.
- After manual price restoration: ProductPricingTest 9 tests / 86 assertions passed.
- After column selector: ProductColumnsTest + ProductPricingTest + CatalogueAdminTest: 40 tests / 342 assertions passed.
- Browser checks verified column presets, all/clear, export URLs, persisted choices, no JS errors and body width 390px on a 390px mobile viewport.
- Browser checks verified five editor tabs, visible manual price, calculator-save synchronization and editor revision updates.
- The final default-collapse change was a small markup-only change; no new full-suite run was performed afterward.
- Do not report all historical full-suite results as a new full-suite run on future edits.

Useful commands from website:
    & '..\.tools\php\php.exe' artisan test --filter='ProductColumnsTest|ProductPricingTest|CatalogueAdminTest'
    & '..\.tools\php\php.exe' artisan test
    & '..\.tools\php\php.exe' vendor/bin/pint path/to/changed.php

Important tests:
- ProductPricingTest: formulas, direct save, stale updates, manual prices, permissions, private docs, literal XLSX cells.
- ProductColumnsTest: selectable/exported fields, validation, saved vs published prices.
- CatalogueAdminTest: import/matching, media, categories, stock and permissions.
- PosSaleTest, CheckoutTest, OnlinePaymentsTest, GatewaySettingsTest.
- AboutPageTest, ContactPageSettingsTest, CatalogueBookTest.

Browser tooling used:
- Node Playwright package at:
  C:/Users/dell/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright
- chromium.launch({channel:'chrome', headless:true})
- Use waitUntil:'domcontentloaded'; external font/assets can delay load.
- .tools/render-*.php fixtures rendered admin views with an in-memory fake auth user, without changing real accounts.
- .tools/check-direct-pricing.cjs and check-manual-price.cjs mock browser pricing responses; real server logic is covered separately by feature tests.
- .tools/check-columns.cjs checks product-grid interactions.
- Some scratch browser scripts expect the selector to start open and may need updating after the final collapse request.
- Do not create public unauthenticated preview/login bypass routes.

## 10. Database and deployment handover

New local migrations include:
- 2026_09_27_100000_create_online_payments.php
- 2026_09_27_110000_create_gateway_settings.php
- 2026_09_29_100000_create_about_pages.php
- 2026_09_29_110000_create_catalogue_pages.php
- 2026_09_29_120000_contact_page_settings.php
- 2026_09_29_130000_product_dna_pricing.php

Product DNA migration:
- products.dna JSON and editor_revision.
- product_pricing_drafts (now reused as calculator-settings storage).
- product_price_reviews (now retained audit history).
- product_documents.
No product-price rewrite in the migration.

Deployment script inspected: website/deploy-update.sh.
It documents production as:
- app /var/www/mammamiacucina-app/website
- deploy account mmcdeploy
- domain mammamiacucina.ca
- PHP 8.4, Nginx/PHP-FPM
- script runs as root after the intended release has been pulled.
These are repository-documented settings, not a newly verified production session.

Script behavior:
- backs up .env, Nginx configuration and commit reference.
- maintenance mode.
- consistent SQLite backup via VACUUM INTO; aborts if database driver is not SQLite.
- backs up storage.
- composer install using lockfile.
- clears config/routes, runs additive migrations, caches config/views.
- reloads PHP-FPM, brings site up and checks public/admin URLs.
- failure leaves maintenance mode in place for investigation.

Before any requested deployment:
1. Review ALL current modified/untracked files, not only the latest feature.
2. Exclude .env, credentials, runtime logs, scratch .tools outputs, private uploads and unnecessary sensitive sample references.
3. Ensure required generated catalogue assets and database/data JSON are included.
4. Verify backups, production target, actual database driver and available runtime.
5. Preserve APP_KEY and storage; use additive migrations.
6. Check scheduler/webhooks for payment deployment.
7. Run relevant tests and smoke checks.
8. Give concrete server commands based on the actual verified release; do not claim success without evidence.

Git code does not synchronize database content or uploaded files:
- About default content is initialized by migration; later local admin edits/uploads require explicit export/transfer.
- Same principle applies to Contact details and uploaded catalogue pages.
- User specifically wants About data on live eventually. Compare current local content with migration defaults before deployment and arrange a targeted transfer of chosen content if necessary.
- Never overwrite the entire production database to achieve content transfer.

Payment scheduler documented in PAYMENTS.md:
    * * * * * cd /var/www/mammamiacucina-app/website && /usr/bin/php8.4 artisan schedule:run >> /var/www/mammamiacucina-app/website/storage/logs/scheduler.log 2>&1
Check existing crontab before adding it. deploy-update.sh does not install the cron entry.

## 11. Documentation priority and stale notes

Read current code and this handover first, then:
- website/PRODUCT_PRICING.md — latest product editor/price/columns behavior.
- website/PAYMENTS.md — gateway setup and payment operational requirements.
- website/POS.md — scanner, barcode, same orders, stock rules.
- website/CATALOGUE.md — flipbook data/assets/uploads deployment.
- website/CATALOGUE-ADMIN.md — import/inventory/media background.
- website/ADMIN-SETUP.md — original admin asset/setup notes.

Known stale documentation:
- README-MMC.md describes an early static-theme phase, claims no cart/checkout backend, and refers to port 8088. This is historical and no longer describes the current app.
- CATALOGUE-ADMIN.md starts by saying storefront/order integration is a later phase. That introduction is outdated.
- Older chat plans referring to New Pricing, approval queues and draft submission are superseded.
- Do not resurrect earlier static product-detail pricing or duplicate checkout flows.

## 12. Next-chat quick-start checklist

1. Read this entire file.
2. Check git status, branch and local instructions. Do not reset uncommitted work.
3. Read the latest product documentation and files relevant to the user's next request.
4. Verify local server and runtime; use public as server working directory.
5. Treat live as unchanged until verified.
6. Ask only for genuinely missing requirements; otherwise implement the next requested fix.
7. Run focused tests and browser checks appropriate to the change.
8. Update this handover if important behavior changes.

Suggested first message from the user in the new chat:
"Read MMC_HANDOVER.md fully. Continue the existing MMC Laravel project from its current working tree. Preserve all existing data and uncommitted changes. Do not deploy yet. Tell me when you understand the current state, then I will give the next task."

This file transfers context; the next environment still needs the actual repository, dependencies and any required database/uploads. A Markdown handover by itself does not contain the application, secret credentials or database backup.

## Latest continuation: Warehouse workflow (29 September 2026)
This section supersedes any earlier statements above about order statuses and outstanding work. Read website/WAREHOUSE.md for the complete new workflow and limitations.
Implemented and migrated locally: POS orders now Completed; website orders can be sent to warehouse; Warehouse User role (dashboard.view + warehouse.pack); per-item packing ticks/notes; return issue to admin with red alerts; reassign/reset rounds; ready/dispatch/delivered/completed. Admin can amend items/prices/customer/delivery/pickup before dispatch, with atomic stock differences and preserved original payment amounts. Added external collection/refund ledger and report accounting. Existing data preserved; no deployment/push made in this task. No warehouse account/password created; assign the new type to a staff account. All current changes remain in the shared working tree alongside prior work.
Validation and files: website/WAREHOUSE.md. Full suite 148 passed /1591 assertions, followed by 36 focused tests/473 assertions including new edge cases. Local URLs remain http://127.0.0.1:18088/admin and /admin/orders. Preview screenshots in .tools/warehouse-*.png use fictional orders, not database fixtures. Gateway provider integration tests are mocked; no claim of real sandbox/live payment testing.

### Warehouse login redirect fix
Login now discards the saved intended URL and selects a role-appropriate landing page: warehouse.pack without orders.manage → /admin/orders; other admin accounts → /admin. Visiting /admin/login while authenticated follows the same selection. This prevents a warehouse user being redirected to /admin/users after login; direct access to User Management still correctly returns 403. Regression tests added to AdminAccessTest.

### Own orders in warehouse queue
Warehouse users now see orders whose created_by matches their account, regardless of status, alongside website orders in warehouse_pending/packing/ready_to_dispatch. A grouped Order::visibleToWarehouse scope is shared by index and detail/print authorization; dates/search/status still apply. Their own POS sales have read-only details and full printable receipts. Other warehouse orders retain packing-only views; other staff's sales remain inaccessible. No new edit/payment permissions are granted. AdminAccessTest + WarehouseWorkflowTest: 25 passed, 245 assertions.

### Orders table update
Orders now default From/To to the first/last day of the current month for all roles. Added Created By (user name, with Website customer/Former user fallbacks), Action heading, server-side sortable headers, 10/20/50/100 page sizes, result counts and Excel/PDF/Print exports. Export uses the same scoped query/date/search/status/sort as the table and includes all filtered rows, not only one page. XLSX uses literal string cells. PDF is a print-ready view using browser Save as PDF, matching Products. Warehouse exports omit financial columns. New regression tests verify month boundaries, scopes, filters and actual XLSX contents. 20 order/workflow tests passed, 346 assertions.

### Pending/completed split (latest; supersedes combined queue)
/admin/orders is View Pending Orders; /admin/orders/completed is View Completed Orders. Admin sees all creators/sources, partitioned by status != completed versus completed. Warehouse Pending only shows website warehouse_pending/packing/ready_to_dispatch; Warehouse Completed only shows completed orders with created_by matching the current user. Shared model detail scope now matches these rules. Both sidebar links use existing warehouse.pack or orders.view access; no role/data migrations needed. Source is its own column (Website / Quick Sale / POS). Created By displays creator name. Fixed literal @include header output by separating adjacent Blade directives; header/body counts tested. Export carries view=completed or pending and the same scope. Current-month default dates retained. 30 order/POS/warehouse tests passed (480 assertions); browser checked 6 warehouse table headers/cells. No live deployment.

### Automatic website warehouse routing
New cash website orders start warehouse_pending with round 1 and sent timestamp. Stripe/PayPal orders wait for verified payment, then route automatically to warehouse_pending; late/released payments remain payment_review. Payment retry guard keeps packing rounds idempotent. Admin no longer needs an initial transfer; return-to-admin/resend workflow is unchanged. Existing eligible local Placed website orders were moved to warehouse_pending with audit events (no stock/payment changes). 48 checkout/payment/order/warehouse tests passed, 700 assertions. Live not deployed.

### Warehouse packing table and scanner
Replaced large item cards with a compact table and customer/address/order summary. Added camera barcode/QR scanning via existing bundled ZXing library plus keyboard/manual scanner input. Matches exact product QR-code value (order snapshot first, current product fallback when historical snapshot is blank), preserves leading zeros, rejects unknown/ambiguous codes. One scan ticks the full row quantity, repeated scans do not increment quantities; save is explicit. Rows with item notes or Mark shortage become yellow and unchecked, including server-side enforcement; unresolved notes block Ready. Admin sees persisted yellow rows and returned order row alerts. Camera starts only on user click, stops on Stop/save/page hide/visibility change, and handles denied access. No extra DB migration. warehouse.css/js contain reusable styles/logic. Packing slip rebuilt with branded header, customer/address, quantities, barcode text, item/status notes, signoffs, A4 print styling; both warehouse Print links use this template. No prices on warehouse slips. Verified 31 tests/535 assertions, browser checks for exact/unknown barcode, issue blocking, simulated decoded camera callback/stop, mobile overflow and print output. Physical camera scan not verified. Preview files .tools/packing-table-desktop.png, packing-table-mobile.png, packing-slip-modern.png/pdf. No real order changes during preview/testing, no live deployment.

### Admin confirmations and status wrapping
User clarified that missing Approvals meant confirmation boxes throughout the system, not restoring product-pricing approvals or adding an approval screen. Added shared admin-confirm.js to normal and modal admin layouts: POST form confirmations run in capture phase before save/locking handlers; GET filters remain direct; POS retains its existing confirmation. Warehouse save/return/ready and resend messages are specific. Existing catalogue deletion and gateway refund messages use data-confirm to avoid duplicate dialogs. Added confirmation before AJAX order status changes and calculated-price saves. Cancellation restores the previous status and prevents requests. Long status badge labels now wrap within the status column; warehouse issue styling is yellow. Browser regression checks passed for badge bounds, cancelled/accepted status requests, form handler cancellation and unaffected GET filters; all three edited JS files pass node --check. No real order changes during testing and no live deployment.

### Visible order item editor
Moved the existing amendment form into the lower Products section, replacing its read-only table for admins on editable orders. Added Remove/Undo buttons with confirmation and Add Item search over active product name, product_code (supplier SKU) and qr_code (barcode/internal code). Existing stock/revision/price/order-history service remains authoritative; quantity 0 deletes only when saved. Customer/delivery fields stay in an expandable section. Existing order totals remain below the editor; Payments & adjustments panel remains hidden. Browser fixture checks passed for removal cancellation/undo and product search; WarehouseWorkflowTest passed 14 tests/256 assertions. No real order mutations during testing.


### Order editor UI correction
New rows now sit in the same four-column product table with matching quantity, unit price and Remove controls. Removed the separate search field and native dropdown combination. Product picker is now one dropdown with search inside, matching name/SKU/barcode and filling current unit price on selection. Keyboard navigation/Escape supported. Browser checked column alignment, selection/price, no matches and removal; no real orders changed.

### Live order editor totals
Added Line total CAD to existing and added item rows. Live integer-cent preview recalculates subtotal, delivery, tax, grand total and balance/refund on quantity, unit-price, selection, removal/undo and charge changes. Unit price remains per item. Tax follows selected manual/recalculate mode with the same basis-point rounding as OrderAmendment. Pickup forces zero delivery. Charge controls are visible outside the customer details accordion. Invalid/incomplete inputs show unavailable totals, not misleading amounts. Preview is not persisted until Save Order Changes. Browser checks passed for all calculation paths; WarehouseWorkflowTest 14 passed/256 assertions. No real orders changed during testing.

### Automatic tax and delivery toggle correction
Removed visible tax mode selector; order editor always submits recalculate, shows readonly tax with percentage, and recalculates on every item change. Server's existing percentage calculation remains authoritative. Aligned fulfillment, delivery and tax as three full-width controls in one responsive row. Delivery/pickup switching remembers the last entered delivery fee, including intentional zero; orders initially pickup use configured delivery default when switched to delivery. Browser checks passed for original/custom/zero fee round trips and automatic percentage tax.

### Full-width responsive homepage
Removed 1920px outer caps from homepage sections; retained bounded inner category/content areas. Desktop masthead now uses a flexible aligned layout with capped content padding; hero spans viewport with bounded height and cover photography. Homepage-only overrides preserve interior pages and existing mobile stacked layouts. Browser checked widths 320, 390, 768, 1024, 1100, 1440, 1920, 2560 and 4800px with no horizontal overflow and full-width hero; mobile menu/category dropdown tested. No live deployment.

### Customer accounts and verified checkout
Added migration 2026_09_29_180000_add_customer_accounts (applied locally): users.phone, users.account_type (individual/business), Customer role without staff permissions. Registration has full name, unique normalized email, phone, account type, confirmed hashed password (10+ chars). Existing email requires login; never reveal account details from email alone. Signed verification links expire in 60 minutes; resend/login/registration throttled. User explicitly chose local log mailer; verification URLs are in website/storage/logs/laravel.log. Production SMTP still needs deployment configuration.
GET and POST checkout require active verified customer via CustomerAccess. Cart survives registration/login/verification. Checkout prepopulates name/email/phone; email is readonly and server-bound to account. Orders.created_by stores customer id, source website. Customer pages /admin/my-orders and /admin/my-orders/{order} use the admin layout with My Orders only; owner/source-scoped queries block access to other customers' orders and omit warehouse/internal notes. Customer account type blocks staff privileges even if assigned another role. Staff AdminAccess redirects customers to their portal.
Shared header account icon links guests to /account/login and customers to their orders; logged-in users see their initial. Registration at /account/register. Customer logout preserves cart but invalidates authentication session. Registration/login clear previous checkout/success session tokens to isolate accounts. No automatic attachment of historic guest orders based on email.
Verified 67 tests/847 assertions spanning customer, checkout, online payments (mocked providers), admin access, order management and warehouse workflows. Browser checked registration/account icon and guest redirect at widths 320,390,768,1100,1440,1920. Additive migration needs running on deployment. No live deployment or real customer orders created in testing.

## Customer management and explicit customer/order relationship (29 September 2026)
- Added customers table (unique user_id and email) and nullable orders.customer_id foreign key. Customer identity/contact fields synchronize when the customer user is saved.
- Migration backfills existing registered customer profiles and website orders with their known created_by user ID. Legacy guest orders are deliberately not claimed by email alone.
- Checkout assigns customer_id from the authenticated customer server-side; repeat orders retain the same customer record. created_by remains the audit actor.
- Customer My Orders/detail authorization now uses customer_id.
- Admin Customer Management: /admin/customers, searchable name/email/phone/ID, order count, last order, verification. Detail includes profile, latest order address, and paginated full order history linking existing admin order views.
- Uses existing orders.view permission; warehouse-only users are forbidden and customers are redirected to their own portal.
- Migration applied locally. Regression suite: 68 passed; customer tests include repeat checkout, stable ID, admin history, and access isolation.

## POS customer accounts and password reset (30 September 2026)
Quick Sale now selects existing customers (search name/email/phone) or creates a customer with mandatory email and first name. POS orders use customer_id with created_by remaining the staff actor. New accounts have random unknown passwords and receive a signed 2-day verification invitation after commit. Invitations verify email without requiring a password, then direct to Forgot Password.
Customer login links to /account/forgot-password. Laravel broker uses hashed, expiring, single-use reset tokens; requests return generic responses and exclude staff/inactive accounts. Reset does not bypass email verification. Local MAIL_MAILER=log still applies.
Validation: 31 feature tests passed (505 assertions); POS JS syntax checked.

## Individual and Business website pricing (30 September 2026)
Existing total_selling_price_cad is Individual. Nullable business_selling_price_cad stores Business; null falls back to Individual until configured. Product Pricing has individual profit_percent and business_profit_percent with separate per-piece/carton previews and atomic saving of both. Identity exposes both manual prices. Shared cost inputs and markup/margin mode apply to both percentages.
Product storefront_price selects Business only for active, email-verified Business customer accounts. Used by homepage, catalog cards/detail, cart, checkout; catalog sort/filter uses the same SQL selection. Checkout independently recalculates and rejects stale quotes. Stored historical order prices remain unchanged. POS currently retains its existing Individual price quotation; this request implemented website tier pricing.
Migration applied locally. Pricing/storefront/checkout regression suite passed, plus Business tier tests covering charge amounts, save, sorting and stale quote rejection.

## Postal-code delivery matrix (2026-09-30)
- Imported source: `website/public/sample/LittleGuys_all-services_RateCard.html`, effective May 1, 2026. Deployable data is `website/database/data/little-guys-delivery.json` (source SHA-256 included).
- 169 postal prefixes, 5 services, 4,500 directional zone cells; 117 Overnight cells are unavailable (`NULL`, never zero/free). All cells independently checked against source HTML in DeliveryRateImportTest.
- Deploy from website: `php artisan migrate --force` then `php artisan db:seed --class=DeliveryRateSeeder --force`. Seeder is transactional/repeatable and also included in DatabaseSeeder. It does not enable delivery or override the warehouse setting automatically.
- General Settings: set warehouse postal code and enable Postal-code Delivery Rates after importing. Local setup enabled with temporary M2N (zone 8); replace with actual warehouse code before launch.
- Checkout accepts Canadian postal prefixes/full codes, selects service, quotes on server, and rejects unknown areas/unavailable routes and stale quotes. Orders snapshot service, origin postal/zone, destination zone, and delivery cents. Existing orders remain unchanged. Admin detail, customer detail, success summary, and shared print show the service.
- Current amount is the matrix BASE RATE, per requested example M2N -> M1P / Bullet = CAD58.76. Source separately lists 15% fuel, 13% HST, selected downtown surcharge and package/weight extras. These are not added by this integration; existing store product tax setting remains in force. Downtown eligible prefixes/package accounting require an agreed policy before adding those extras.
- POS/manual admin delivery charges remain manually entered; customer website checkout uses the matrix when enabled.

## Admin Delivery Rates (2026-09-30)
- Added `/admin/delivery-rates`, linked in the Settings sidebar and General Settings. Requires `settings.manage` for both viewing and editing.
- Visual 30x30 service matrix with sticky zone labels, price heat shading, unavailable states and highlighting of the calculated route. Click a cell to edit that directional route's CAD base amount or availability.
- Postal-code calculator compares all five services; warehouse origin defaults from General Settings. Searchable postal-prefix directory included.
- Additive migration `2026_09_30_030000_delivery_rate_revision.php` tracks revisions. Stale admin edits are rejected; settings/rate locks coordinate with checkout. New quotes use saved rates immediately and existing checkout snapshots are revalidated. Historical orders are unchanged.
- DeliveryRateSeeder now preserves rows edited through the admin panel (revision > 0), including deliberately unavailable routes, when deploying/reseeding.
- Tests: AdminDeliveryRateTest, DeliveryQuoteTest, DeliveryRateImportTest: 7 passed / 4747 assertions. Read-only rendered browser preview checked 900 matrix cells, five comparison cards, availability controls and widths 390/768/1440 with no page overflow. No real accounts, orders or route prices changed by UI checks.

## Unified delivery checkout and saved route details (2026-09-30)
- Supersedes the earlier POS flat-charge limitation: when postal-code delivery is enabled, both website and POS require a supported Canadian destination and available service. Service dropdowns show all five current prices; totals update after selection. Pickup stays free. Disabling matrix delivery explicitly restores the existing flat-rate fallback in both channels.
- Website `/checkout/delivery-options` and protected POS/order-edit option endpoints share DeliveryQuote. POS encrypted reviews include the full route snapshot and matrix mode; completion revalidates destination, country, origin, service and amount. Stale quotes cannot silently charge an old or different rate.
- Migration `2026_09_30_040000_order_delivery_details.php` adds `orders.delivery_to_postal`, `delivery_service_name`, and `delivery_rate_cents`, alongside existing service code, origin postal and both zone fields. Existing matrix orders are backfilled from their own saved amount/destination, not current rates. Older flat-rate orders are clearly labelled as having no recorded courier/origin.
- Shared order-delivery partial now shows saved service, From/To postal codes and zones, and the saved base charge in customer/admin detail, success, authenticated tracking, payment, warehouse, POS own-sale and shared print/PDF views. Order register exports include these fields too. Public number-only status lookup remains private-data limited.
- Admin amendments use service/postal pricing for matrix orders. Same-route edits retain historical delivery prices; changed routes must match a freshly calculated rate. Pickup clears all courier snapshot fields. Legacy status/charge controls cannot manually overwrite a matrix order fee.
- Backend checks: 28 tests covering POS, admin delivery and warehouse; 48 tests covering checkout, customer ownership, matrix POS/amendments, payments and tracking. All passed (one shared test between runs). Browser fixtures verified service price labels, Bullet/Overnight total changes, invalid destination blocks, pickup, admin reprice/pickup round trip and PDF fields. Fixtures did not create real sales or charge payments.
- Local warehouse remains the user-authorized temporary M2N; update General Settings before use with the actual warehouse origin. Rate amounts remain base rates as previously agreed.
