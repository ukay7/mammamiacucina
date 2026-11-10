# Company headers on documents

General Settings → Document Header controls the company legal name, mailing address, document warehouse address and GST/HST number. Phone and email use the same saved fields as Contact Us. The document warehouse address is separate from checkout pickup instructions.

The migration `2026_11_10_120000_add_document_header_settings` adds four nullable fields and initializes the approved screenshot values:

- Company: Mamma Mia Cucina INC.
- Mailing address: 1 Sir Bodwin Place, Markham, ON L3P 2X7
- Warehouse: 471 Jevlan Dr, Woodbridge, ON L4L 8E3
- GST/HST: 737385034RT0001

Existing phone, email, pickup instructions, logo, charges, settings revision, financial records and stock history are preserved. Optional document addresses and GST/HST can be cleared; blank lines are omitted. Settings retain permission checks, validation, escaped output and stale-edit protection.

`documents.header` and `documents.header-style` provide the shared layout for order confirmations, invoices, payment/correction/refund receipts, customer payment receipts, supplier orders, inventory reports, product catalogue exports, quotations, packing slips and order-list print/PDF exports. Special-purpose barcode labels and customer file-cover layouts retain their established designs.

Existing document disclaimers remain in place. Toronto date/time formatting is unchanged. Previously archived attachments are preserved; newly generated documents use the saved header settings.

## Deployment and checks

Pull `main`, run `php8.4 artisan migrate --force`, rebuild Laravel caches, restart queue workers and reload PHP 8.4 FPM. No Composer dependency or environment change is required. Verify the new values in General Settings → Document Header, then generate an invoice and payment receipt. Toronto dates continue to display EST or EDT according to the date.

Validation includes Settings save/permissions/lengths/stale edits, optional-field hiding and escaping, migration preservation of existing settings, unchanged historical financial data, shared disclaimers, and the affected PDF/print routes. The migration was checked on isolated MySQL before local application, and seven document previews were rendered for visual review. Interactive browser verification could not complete because the computer-use tool could not reliably identify the current browser URL.
