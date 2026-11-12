# Company details on documents

General Settings → Document Header controls the company legal name, mailing address, document warehouse address and GST/HST number. Phone and email use the same saved fields as Contact Us. The document warehouse address is separate from checkout pickup instructions. Per the user's 12 November 2026 update, GST/HST appears at the bottom right in the document footer, never in the company header.

The migration `2026_11_10_120000_add_document_header_settings` adds four nullable fields and initializes the approved screenshot values:

- Company: Mamma Mia Cucina INC.
- Mailing address: 1 Sir Bodwin Place, Markham, ON L3P 2X7
- Warehouse: 471 Jevlan Dr, Woodbridge, ON L4L 8E3
- GST/HST: 737385034RT0001

Existing phone, email, pickup instructions, logo, charges, settings revision, financial records and stock history are preserved. Optional document addresses and GST/HST can be cleared; blank lines are omitted. Settings retain permission checks, validation, escaped output and stale-edit protection.

`documents.header`, `documents.header-style` and `documents.registration` provide the shared header and GST/HST footer for order confirmations, invoices, payment/correction/refund receipts, customer payment receipts, supplier orders, inventory reports, product catalogue exports, quotations, packing slips and order-list print/PDF exports. The registration partial is included at the end of every template using the shared header. Footers stay together across page breaks, and blank registration values remain hidden. Special-purpose barcode labels and customer file-cover layouts retain their established designs.

Existing document disclaimers remain in place. Toronto date/time formatting is unchanged. Previously archived attachments are preserved; newly generated documents use the saved header settings.

Order and payment-receipt delivery details show the destination, saved courier, postal-code route, zones and original route rate when recorded. Pickup orders show their saved pickup instructions. New invoices capture these fields at issuance and render them from the invoice snapshot, so later order or settings changes do not replace the original details. Older invoice snapshots are not backfilled with current route data. The saved route rate is labelled separately from the delivery amount charged in the totals.

## Deployment and checks

Pull `main`, run `php8.4 artisan migrate --force`, rebuild Laravel caches, restart queue workers and reload PHP 8.4 FPM. No Composer dependency or environment change is required. Verify the new values in General Settings → Document Header, then generate an invoice and payment receipt. Toronto dates continue to display EST or EDT according to the date.

Validation includes Settings save/permissions/lengths/stale edits, optional-field hiding and escaping, migration preservation of existing settings, unchanged historical financial data, shared disclaimers, and the affected PDF/print routes. Footer placement is checked in rendered HTML. Twelve PDF previews cover all nine shared-header templates, including a long order; their PNG renderings were visually reviewed. Single-currency inventory valuation uses a correctly spanned summary cell so its second information panel does not collapse or cause unnecessary extra pages. Existing-record fingerprints were unchanged by preview generation, and PHP GD was enabled.
