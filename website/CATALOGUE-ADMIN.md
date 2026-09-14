# Catalogue administration

The catalogue is managed separately from the current storefront. All 23 workbook fields are retained; storefront display and order integration are a later phase.

## Admin pages

- `/admin/categories`: create/edit categories; General remains the default. Used categories can be deactivated, but not deleted.
- `/admin/imports`: upload an XLSX, review rows, then commit. Download the original workbook or a blank template here.
- `/admin/products`: search, filter, view/edit all details, add a product, and move selected products to another category. Deactivate products instead of deleting stock history.
- `/admin/inventory`: set opening balances, add/remove stock, configure low-stock thresholds and review the audit history.

Each module has separate view/manage permissions under User Types & Permissions. Super Admin has all modules. The existing Administrator role receives the new permissions in the catalogue migration. Other roles must be granted access explicitly.

## Import rules

- `.xlsx` only, up to 10 MB uploaded / 50 MB expanded, at most 10,000 worksheet rows.
- Use all 23 original headers, in any order. Title rows before the headers are supported.
- Keep identifiers formatted as Text in Excel to retain leading zeros.
- The uploader creates new products and updates existing matches. Match product code first, then QR CODE. Comparisons ignore case and surrounding whitespace. Names are editable details, never matching keys. If neither identifier matches, insert a new product in General with stock not yet set.
- Only changed, nonblank catalogue details are saved. Identical records remain unchanged, including timestamps. Blank cells retain existing values. Category, activation, slug and inventory are preserved. Use Edit Product to clear a field deliberately.
- If code and QR identify different records, flag the conflict. Shared product codes require the existing QR to distinguish variants. Multiple rows targeting one product are invalid. The batch is planned before any saves and checked again under locks at commit.
- Reviews show Will insert, Will update or Unchanged. History shows created, updated and unchanged counts separately from reference/header rows. The uploader has no category selector.
- Any invalid product row blocks the whole import. Fix the workbook and upload again. A successful import is transactional; submitting the same preview again does not import twice.
- Notes/links without product identity are preserved as reference rows, not products. Original files are held in private storage and downloaded only through authenticated import routes.
- All supplied numeric values are retained to eight decimal places; blank values remain null. Supplier Unit Price (EUR) 2, Rate Exchange (CAD), and Surcharge Increase (CAD) retain their source meaning and are not reinterpreted or recalculated.
- Formulas use Excel's last saved computed values. Save/recalculate in Excel before uploading. The original file and formula text are preserved in the import record.

## Inventory

Invoice quantity is not live stock. Opening stock begins as **Not set**, and must be entered using Set stock. Stock uses the product's UoM, accepts up to three decimal places, and cannot become negative. Every update records before/after, change, reason, user and date. Stale stock forms are rejected to avoid overwriting another adjustment.

## Deployment

1. Install locked Composer dependencies (`composer install --no-dev --optimize-autoloader`). The XLSX reader requires PHP 8.3/8.4 and ZIP, XMLReader, DOM and Fileinfo extensions.
2. Configure the production database and retain the existing application key. Back up the database before running migrations.
3. Run `php artisan migrate --force`, then `php artisan view:cache`.
4. Ensure `storage` and `bootstrap/cache` are writable by the application user; licensed SmartAdmin assets must already be installed privately as described in ADMIN-SETUP.md.
5. Upload the workbook through Product Uploader. The local SQLite records are not automatically deployed.

Optional CLI import from the `website` directory:

```sh
php artisan catalogue:import /private/path/catalogue.xlsx
# Add --commit to import validated rows immediately.
```

Source workbooks containing supplier prices should be stored outside `public`. A private copy of the supplied workbook is in `storage/app/private/catalogue-source`; the importer also keeps its own private original copy. The source in public/product_sample is excluded from Git and remains open in Excel; close Excel before removing that public source copy.

## Verification

Run `php artisan test`. Catalogue tests cover original field preservation, leading zeros, missing supplier codes, decimal values, duplicate/repeat imports, invalid row blocking, upload/download and screen rendering, category reassignment, permission enforcement, stock history, stale forms and negative-stock rejection.



## Product modals and media

Manage Products opens View and Edit in modals. Saving in the editor keeps the modal open; closing it refreshes the list. Product Images & Videos accepts up to 10 JPEG, PNG, WebP, GIF, MP4 or WebM files per save, 50 MB each. Files are stored privately and previewed through a product-view permission route. New uploads append in selection order; set Display order and save to arrange images and videos together. Mark Remove on save to delete an attachment. Existing spreadsheet imports preserve media.

Run migrations for the product_media table. For production video uploads, set PHP upload_max_filesize=50M and post_max_size=512M, and the site's Nginx client_max_body_size to 512m, then reload the relevant services. Existing server limits can reject large uploads before the application receives them. Local bundled PHP limits are configured accordingly.

## Home banners

Admin > Website > Home Banners manages the home slider. Four original banners are seeded by the banner migration, preserving their artwork, heading line breaks, subheadings and button labels. Add/edit image, heading, subheading, button text, accessible image description, priority and active status. Lower priorities display first, ties by ID. Every button targets the product grid directly. Recommended artwork is 2129 × 739 px with the centre kept clear for text, JPEG/PNG/WebP up to 10 MB. Blank image inputs keep existing artwork. Uploaded replacements live in private storage and are served by a controlled image route; inactive uploads are visible only to authorised banner viewers. New banners.view/manage permissions are available under User Types & Permissions. Deploy the new migration before serving the updated home page.

## Multiple product categories

Products belong to one or more categories through category_product. The migration preserves every existing assignment. In the product editor, tick all relevant categories; at least one is required. Admin product filtering matches any assigned category and category counts use the same relationship. Bulk Add category keeps existing assignments; Replace all categories replaces the complete selection. The original category_id remains as a compatible primary category. New Excel products receive General, and repeat imports preserve all category links. A product retains one inventory and media collection regardless of the number of categories. The public product grid is still the static design and will use these relationships when connected to the catalogue.
