# Toronto business time

All business-facing clocks use `America/Toronto`. PHP's timezone database selects EST (UTC-5) or EDT (UTC-4) for the date being displayed. The application clock and timestamp persistence convention remain UTC (`config('app.timezone')`). Do not change `APP_TIMEZONE`, the server clock or the MySQL session timezone as part of deploying this patch.

`App\Support\BusinessTime` provides immutable display conversion, the current Toronto calendar date, Toronto datetime input conversion, and exclusive UTC report boundaries. SQL day grouping uses the timezone transitions supplied by PHP and supports MySQL and SQLite without installing MySQL timezone tables. Report filters retain timestamp range comparisons so indexes remain usable.

## Coverage

- Customer and admin order lists/details, warehouse views, packing slips, inventory history, stock counts, supplier orders and customer history.
- Order confirmations, invoices, payment/correction/refund receipts, customer payment receipts, supplier PDFs, report PDFs and newly generated email documents.
- Order/payment/inventory reports and daily dashboard charts, including CSV and Excel timestamp fields.
- Toronto date entry for order corrections and stock-count schedules; Toronto calendar defaults and overdue checks for receipts, cheques, delivery planning and notes.

Historical timestamps, invoice snapshots, payments and inventory are not rewritten. Reprinting converts a copy of each timestamp. Previously archived emails/files remain preserved. Date-only values (payment date, cheque date, due date and planned delivery date) remain the same calendar date. Correcting an Order Date still leaves `created_at` unchanged and records a dated audit entry.

The spring daylight-saving gap and repeated fall-back hour cannot be represented unambiguously by a datetime-local field. Date entry rejects those times with a validation message rather than silently shifting or guessing them. Already-recorded timestamps in either occurrence of the fall-back hour display correctly with EDT or EST.

## Deployment

This patch needs no schema migration, data conversion or Composer dependency update. From `/var/www/mammamiacucina-app/website`, pull `main`, clear/rebuild Laravel caches, restart queue workers and reload PHP 8.4 FPM. Maintenance mode should always be lifted if a deployment command fails.

Read-only verification:

```bash
sudo -H -u mmcdeploy php8.4 artisan tinker --execute='dump(config("app.timezone"), config("app.business_timezone"), \App\Support\BusinessTime::now()->format("l, d M Y H:i T"));'
```

Expected zones: `UTC`, then `America/Toronto`. A November example: `2026-11-02 18:12 UTC` displays as `Monday, 02 Nov 2026 13:12 EST`. A July example at the same UTC time displays as `14:12 EDT`.

## Validation (2026-11-10)

- All 10 Toronto-specific tests pass, covering DST offsets, 23/25-hour reporting days, midnight boundaries, receipt date validation, stock schedules, order corrections and unchanged historical document data.
- All 65 affected-workflow regression tests pass (reporting, dashboards, receipts, warehouse operations and shared documents; this group includes nine of the timezone tests).
- MySQL day-bucket expressions were checked directly. Sample confirmation, invoice and receipt PDFs were rendered and visually reviewed. Read-only checksums of existing orders, items, events, invoices, settlements, payments and inventory matched before and after rendering.
- The complete suite was run. Eleven unrelated failures concerning existing navigation, customer/receipt text and older payment-flow expectations were reproduced against the unmodified previous commit `805291c`. They are not fixed by this timezone patch. The existing single skipped test remains skipped.

```bash
php8.4 vendor/bin/phpunit --filter TorontoTimeTest
php8.4 vendor/bin/phpunit --filter 'TorontoTimeTest|WarehouseWorkflowTest|BusinessReportsTest|InventoryDashboardTest|CustomerReceiptTest|DashboardNotesTest|SupplierOrderDocumentTest|DocumentProductImagesTest'
```
