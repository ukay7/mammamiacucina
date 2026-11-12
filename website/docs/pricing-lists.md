# Pricing Lists

Open **Reports → Pricing Lists** (`/admin/pricing-lists`). Create a list, choose individual or business base pricing, optionally select a customer or enter a recipient, then select products and adjust each/box prices. Lists support save, view, edit, duplicate and soft delete.

Filters include product/code/pack search, multiple categories, supplier, active status, selected products, missing prices, box price range and sorting. Filters never discard selected products. A category checkbox affects the entire displayed category, including its hidden products; Select/Clear visible affects only filtered rows. Products assigned to multiple categories match any selected category, but appear once under their primary category.

Review & save and Review & print PDF open the full document for confirmation. Only selected products are saved/printed, including selections hidden by filters. A confirmation expires after 20 minutes. Saved lists also require a confirmation before opening their PDF. Printing sends no email and changes no order, invoice or catalogue record.

Each new list receives a unique sequential reference such as **PL-00001** on its first valid preview. Returning to edit and previewing the same open draft retains that number, including after the confirmation expires. Saving, editing and printing keep it; duplicating assigns a new number. Reserved or deleted numbers are never reused, so abandoned previews may leave gaps. Existing saved lists keep their previous references. Product and whole-category selection checkboxes appear on the left of the editor table.

## Price and snapshot rules

- Individual prices use `total_selling_price_cad`. Business prices use `business_selling_price_cad`, falling back to individual prices when absent (zero is a valid price).
- Consistent with existing quotations, catalogue prices without a saved calculator choice are treated as box prices. An explicit saved calculator choice of `piece` uses the each price. DNA `sale_unit` is a planning field and does not override the saved price basis.
- Pack quantities come from calculator inputs, then DNA pieces per carton, then product pieces per pack. When a positive whole quantity is known, changing each computes box and changing box computes each, rounded to cents. Unknown quantities allow independent prices. Blank means unspecified; zero prints $0.00. Each list supports 1–500 products and prices up to CAD 1,000,000.
- Tier changes preserve custom prices. Reset selected prices explicitly returns them to the list's captured base pricing. Existing saved item descriptions, pack details, categories, images, base prices and company branding are snapshots. New products added later capture their current catalogue information. Duplicating retains the original snapshots and dates for review/editing.
- No product price or inventory updates occur. Customer association is optional. Lists remain usable if an original product is later deleted.
- Prices and fields are validated server-side. The cached confirmation is bound to its staff user, list and revision; saving uses precisely the confirmed payload. Repeated saves of a confirmed token are idempotent. Concurrent edits and stale deletes require reloading.

## PDF and access

PDFs use `resources/views/documents/pricing-list.blade.php` and the shared document styles, company header and GST/HST footer. The same template supplies confirmation and saved-list previews. A4 PDFs have category groups, Code/Product/Pack/Each/Box columns, optional photographs and page numbers. GST/HST stays in the bottom-right document footer. All values are from the saved list, never the example photographs.

Access is controlled through the existing Roles & Permissions screen: Pricing Lists page access permits viewing/printing; its manage action permits create/edit/duplicate/delete. Customers and unassigned staff cannot access the module. Responses are private and uncached. PHP GD is required for PDF logos/photos; remote PDF resources and embedded PHP are disabled.

## Installation and verification

Two additive migrations create `pricing_lists` and its numbering reservations: `2026_11_12_170000_create_pricing_lists.php` and `2026_11_12_171000_add_pricing_list_numbers.php`. The latter backfills existing pricing-list references without renumbering them. No product/customer/order columns are modified. Deploy the migrations using the usual deployment account, refresh config/views, and verify PHP GD is enabled. Do not run `migrate:fresh` or reseed an existing installation.

Focused verification:

```sh
php artisan test tests/Feature/PricingListTest.php tests/Feature/GroupedStaffPermissionsTest.php tests/Feature/QuotationTest.php tests/Feature/OrderDeliveryDocumentsTest.php
```

Browser checks cover category selection with filters, retained hidden selections, linked price entry, preservation of custom prices when changing tiers, full confirmation content, and confirmed save/PDF opening. Sample data for visual review belongs only to the separate SQLite preview database, not customer records.
