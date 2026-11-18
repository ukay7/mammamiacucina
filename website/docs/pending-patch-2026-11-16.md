# Release patch — 16 November 2026

Changes bundled for the requested release. Pushing the patch does not deploy it; run the commands below on the live server.

- Super Admin module home page after login, retaining the sidebar.
- Business names in Dashboard Payment to collect and its detailed listing.
- Small business-customer icon throughout customer/order interfaces and documents, with a General Settings uploader and bundled gold Bootstrap icon.
- Super Admin-only Quick Sale / POS drafts, saved on the server and resumed through the top-right Draft Orders dialog.
- POS product browser redesign: unified search/QR/barcode input, category tabs, a horizontal product shelf with pagination, quantity selection before adding, and a product-details modal. Product prices respect the selected customer's pricing and product discounts. Scanning finds the product for quantity selection; Add confirms the cart change.
- POS customer panel uses half the desktop width; the other half shows up to three customer favourites and three excess-stock suggestions, with photos, product details, quantity controls and Add buttons. Favourites use completed-order frequency; stock suggestions compare current inventory with the last 90 Toronto calendar days of completed-order sales. Recommendations use the selected customer's prices and discounts and refresh when the customer changes or a draft resumes. See `pos-recommendations.md`.
- POS layout polish: equal, independently flowing desktop columns keep products immediately below the customer selector and the sale below suggestions. Compact recommendation controls, consistent gaps and responsive layouts avoid the previous empty space and oversized Add buttons.
- Financial document layout: payment instructions occupy the space to the left of the amount breakdown in order confirmations, invoices and order receipts. Account receipts, refund receipts and credit notes use the same shared layout for their existing verification/refund details. Quotation payment terms appear beside the totals. Applies to browser print and generated PDFs; values and saved snapshots are unchanged. Pricing-free delivery forms and other non-payment documents retain their purpose.
- Shared document header alignment: company name and document title start at the same height, including wrapped titles, across print/PDF templates using the shared header.
- Quotations: Duplicate is available from the list and view for users with quotation-management permission. Select a recipient through the searchable customer/company picker and confirm the validity date; a new numbered quotation opens for editing with the original saved products, quantities, prices, discounts, tax, terms and notes. The original and catalogue are unchanged, stale source revisions are rejected, and no email is sent. New recipient details are freshly snapshotted. Existing future validity dates are suggested; expired quotes default to 30 days from today in Toronto. Small embedded product photos appear in the shared quotation view, browser print and generated PDF, with a placeholder when no photo is available. No additional migration is required.

Required new migrations, in order:

1. `2026_11_16_120000_add_business_customer_icon.php`
2. `2026_11_16_130000_create_pos_drafts.php`

Both are additive. Preserve private storage and include the bundled icon/license and new public assets. The local development database has these migrations; production still needs them when this patch is released. See `business-customer-icon.md` and `pos-drafts.md` for behavior and verification details.

## Live deployment

Run in PuTTY as root on the existing PHP 8.4 server. The subshell stops on failure; if maintenance has started, resolve the reported error before bringing the site back online. This does not back up or reset the database, reseed data, send test emails, or change the scheduler.

```bash
(
  set -e
  cd /var/www/mammamiacucina-app/website
  php8.4 -r 'if (!extension_loaded("gd")) { fwrite(STDERR, "PHP GD is required. Install php8.4-gd first.\n"); exit(1); }'
  sudo -H -u mmcdeploy php8.4 artisan down --retry=60
  sudo -H -u mmcdeploy git pull --ff-only origin main
  sudo -H -u mmcdeploy composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  sudo -H -u mmcdeploy php8.4 artisan config:clear
  sudo -H -u mmcdeploy php8.4 artisan route:clear
  sudo -H -u mmcdeploy php8.4 artisan migrate --force
  sudo -H -u mmcdeploy php8.4 artisan config:cache
  sudo -H -u mmcdeploy php8.4 artisan view:cache
  sudo -H -u mmcdeploy php8.4 artisan queue:restart
  sudo systemctl reload php8.4-fpm
  sudo -H -u mmcdeploy php8.4 artisan up
  sudo -H -u mmcdeploy git log -1 --oneline
)
```

After deployment, sign in as Super Admin to verify the module home page and POS drafts/recommendations. Open Quotations, duplicate an existing offer to the intended recipient and review its products/prices before sharing. Check the product photos and header alignment in Print / Save PDF. No Node build or additional cron entry is required; the new front-end assets are checked into `public` with versioned URLs.

## Initial release verification

- Full SQLite suite: 622 tests, 13,057 assertions; 614 passed, seven failed, one MySQL-only test skipped. The long PDF-heavy run used a CLI-only `memory_limit=1G`; no runtime/server setting changed.
- All seven failures were reproduced against the unchanged last-pushed commit `5be9c2a` using an isolated source snapshot and in-memory test database. They are existing failures in `CompanyLogoMigrationTest` (one), `CustomerAccountTest` (two), `HelcimPaymentsTest` (three), and `OnlinePaymentsTest` (one). This release does not fix or suppress them.
- The existing failures concern the expected document address, printed order-number casing, empty order-history wording, gateway information/refund/balance text on documents, and the manual gateway-resolution stock-validation scenario. They require separate follow-up; do not treat the full suite as entirely passing.
- PHP and JavaScript syntax checks passed, all Blade templates compiled, and the new named routes registered. Quotation copy/edit/save, mobile product-table layout, and single/multi-page PDFs were verified. Earlier patch checks also covered POS draft save/resume/checkout, product suggestions, responsive layout, and MySQL draft/recommendation queries.

## Follow-up: seven existing failures

The follow-up restores the active gateway's customer-facing references in the shared financial document payment panel: provider, Helcim invoice reference where applicable, and transaction reference. Test-mode payments are labelled. Checkout tokens, checkout URLs and credentials are excluded; replaced gateway attempts are hidden, and separate settlement receipts retain their own payment reference. Rendering does not alter saved orders or payments. The shared A4 layout, invoice identity, GST/HST footer and document disclaimers remain intact.

The other failures were outdated expectations: document warehouse addresses are separate from checkout pickup instructions; printed order references use uppercase; the customer history uses the current empty-state text and paginated order counts; and document balance/refund assertions must pair labels with their amounts across table cells. The gateway manual-resolution test now checks the existing shortage workflow introduced in `ef8ecb5`: verified payment can be recorded with negative stock, the shortage and movement are recorded, and retries cannot deduct twice. Stock and payment rules were not changed to satisfy old tests.

Three additional document tests cover gateway-reference rendering, escaping, private-token exclusion, unchanged saved data, and separation from manual receipts/replaced attempts. Helcim and Stripe PDF previews, including a long transaction reference, were visually checked. No new dependency or migration is required.

Final follow-up verification: the full suite completed with **625 tests, 13,176 assertions, zero failures/errors, and one MySQL-only skip** (624 tests passed on SQLite). That remaining MySQL-only case passed separately on the disposable MySQL test database with three assertions. All seven original failures are resolved. PHP syntax and diff checks passed.

## Follow-up: compact Super Admin home

The module home now fills the available content width. Its three groups sit side by side, with two columns of cards per group, so all 18 modules fit in three aligned rows on desktop. Shorter laptop windows use tighter spacing. The sidebar scrolls independently instead of stretching the main page; tablet and mobile layouts reflow without hiding module links. These styles apply only to the Super Admin home page.

Browser checks confirmed all 18 cards fit without page scrolling at 1920 x 900, 1366 x 768, 1366 x 650 and 1280 x 720. Tablet and mobile checks passed without horizontal overflow or clipped card content. The existing home-page tests passed: three tests, 84 assertions. No migration, dependency or asset build is required.

## Follow-up: business alerts, delivered-order corrections and Refund Due

Business registrations now queue a detailed internal approval alert to `info@mammamiacucina.ca`. Only Super Admin can edit delivered/completed orders; each change retains the original invoice and payment amounts and creates a numbered before/after adjustment document. Delivered quantities return to stock only with an explicit saleable-return confirmation. Refund Due appears on orders and customer profiles; overpayment refunds settle the difference without creating another sales credit. No wallet is introduced.

This follow-up requires the additive `2026_11_18_090000_add_delivered_order_revisions.php` migration. The normal release commands above apply it. Existing outbox scheduling handles business alerts without another cron job. See `business-registration-and-delivered-revisions.md` for behavior and `release-2026-11-18.md` for the combined follow-up release.
