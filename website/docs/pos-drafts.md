# Quick Sale / POS drafts

Super Admins can use **Save draft & start new** from the cart or checkout. Confirm an existing customer and add at least one product first. A newly created customer can be selected once eligible to place orders. Delivery/payment fields can remain incomplete while drafting.

**Draft Orders** at the top right opens a searchable, paginated dialog. Each draft has a `DRAFT-00001` style reference, customer/business name, quantity, saved time in Toronto and saving admin. Super Admins share access; ordinary administrators, warehouse staff and customers cannot list, read, save, delete, download attachments from or convert drafts.

Resume restores the customer, product quantities, product search/category, fulfillment method, alternate address, delivery schedule, phone, notes, payment choice and any selected receipt/cheque attachment. If another cart is open, switching asks to save it first. Saving an open draft updates that draft and clears the POS for the next sale. Closing the page with unsaved changes still warns through the existing browser unload handler.

Drafts are stored in `pos_drafts`, separately from orders. They do not create invoices, payments, warehouse work, email notifications or inventory movements. An alternate delivery address is only remembered on the customer account when the actual order is placed. Prices and customer eligibility are checked again by the normal POS checkout; saved delivery dates are retained and must still pass normal delivery validation.

Draft save retries use a unique client key and request hash. Revisions reject stale saves, deletes and checkout attempts. Checkout locks the draft and links it to the actual order inside the existing POS database transaction, so a competing checkout cannot convert it twice. A retry using the same sale reference returns the existing order. Converted drafts no longer appear in the active list.

Optional JPG/PNG/WebP/PDF receipts retain the POS 5 MB limit and use private local storage. The authorized resume endpoint restores the file for the normal order receipt-upload flow. Draft deletion is soft deletion, retaining the record and attachment for recovery while removing access from the draft interface. Converted draft records also remain for traceability. No new queue or cron task is needed.

## Deployment

Ship with the pending patch and run `database/migrations/2026_11_16_130000_create_pos_drafts.php` before opening POS. Include the new JS/CSS and Blade partial. Apply the patch's other pending migration for the business customer icon as well. Normal private-storage write access is required; no PHP upload-limit increase is needed for drafts.

## Validation

`tests/Feature/PosDraftTest.php` covers authorization, complete state and attachment restoration, incomplete drafts, receipt replacement/removal, retry deduplication, stale changes, conversion, rollback, current-price checks, and listing without sorting the large JSON snapshot. Run alongside the existing POS, delivery-address and delivery-schedule tests.
