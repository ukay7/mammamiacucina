# Customer Documents

Customers can open **My Documents** from their account navigation, orders, or profile. The same searchable library is in the admin customer's **Documents** tab and below the edit form. It lists 20 entries per page with document name, type, related number, Toronto date, View, and Download actions.

The library reads existing records automatically; it does not require a migration, backfill, copies of uploads, or a scheduled job. New linked documents appear on the next page load.

## Included records

- Order confirmations for the customer's orders, including completed orders.
- Issued invoices with their saved invoice snapshots and unique invoice numbers.
- Original recorded payment receipts, settlement receipts, refunds, and payment adjustments.
- Every saved customer receipt/cheque event version, including later corrections. Pending cheques remain acknowledgements, not confirmed payments.
- Existing order transfer proofs, settlement attachments, and customer receipt-line uploads. Missing files are omitted; direct requests return 404.
- Quotations linked to the customer.
- Exact saved PDF attachments from accepted order-confirmation and payment-receipt emails. Office notifications, authentication messages, email bodies, and delivery administration are excluded.

View opens the printable document or original upload. Download renders a PDF using the shared A4 theme or returns the stored file bytes. Invoice and receipt snapshots are preserved. Current balances remain explicitly separate from historical figures. The shared GST/HST footer stays at the bottom right. PHP GD is required to render PDFs with the company logo.

## Access and scope

`CustomerDocuments` centralizes listing and file ownership checks. Every customer request is scoped to their own customer record and passes the existing active-account, verified-email and business-approval middleware. IDs or query parameters cannot select another customer's documents. Responses are private and are not cached.

Admin access follows existing permissions: order documents require order access and respect restricted warehouse visibility; quotations require quotation permission; customer account receipts/proofs remain super-admin-only for staff; saved email PDFs require email-management and order-view permissions. Customer access to their own documents is independent of these staff restrictions.

Soft-deleted orders and their linked invoice/settlement/email entries are excluded, following the existing customer order history. Customer account receipt history is retained as its own financial record. Existing uploaded filenames are shown where stored; legacy files without an original name receive a descriptive record name. This feature gathers existing supported files and does not add a new general-purpose upload form.

## Validation

`CustomerDocumentsTest` covers the customer profile/library and both admin screens; all document sources; view/download ownership isolation; real PDF generation; unchanged invoice/receipt snapshots; uploaded and archived file bytes; missing files; staff/warehouse restrictions; login gates; search, type filtering, pagination, newly created records, and deleted orders. Tests use SQLite in memory and fake private storage.
