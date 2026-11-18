# Business registration alerts and delivered-order corrections

## Business approval email

A successful public business registration queues one internal alert to `info@mammamiacucina.ca`. Override that recipient with `BUSINESS_NOTIFICATION_ADDRESS` if needed. The editable `business_registration_notification` template includes the customer number, contact details, business name/contact/BIN, available address, Toronto registration time, verification state and an authenticated admin review link. HTML and plain-text versions are sent through the existing tested SMTP/Resend settings and email outbox. Individual signups, failed validation and verification resends do not create duplicate alerts. Approval and primary-email verification remain separate requirements.

The existing `emails:deliver` scheduled command processes the alert. No extra cron entry is needed when Laravel's scheduler is already running. SMTP outcomes needing review stay visible in email history; Resend retries retain the original idempotency key. Deployment does not send retroactive alerts for existing applications. No actual message was sent during development.

## Delivered / completed order edits

Only an active Super Admin can use the existing item/customer/fulfillment editor after delivery or completion. The server checks this even if someone submits a request manually. Dispatch/cancellation and unresolved payment/refund locks remain in place. Changes require a reason and a current order revision. Delivered/completed status is retained; an edit does not itself confirm replacement delivery.

Each correction records an `ADJ-` number, editor, date, reason, before/after item and financial snapshots, tax difference, and inventory-return choices. The original invoice and recorded collections are preserved. Adjustments are available from the order and the customer's Documents area, with an A4 print/PDF using the shared branding and GST/HST footer. Current invoice balances are identified as current and linked to the original document total.

Reducing delivered quantities does not automatically restock goods. Super Admin must explicitly confirm receipt of a saleable return. Added/increased quantities use the existing inventory rules. Existing sales credits and pending refund payments are considered before accepting a revision.

## Refund Due

The order and customer profile show Refund Due independently of outstanding debts and the credit allowance. There is no wallet or automatic application to future orders.

The refund form distinguishes:

- **Return / sales credit:** the existing flow, which reduces the sale and records the payout together.
- **Refund an overpayment:** returns an existing negative order balance without issuing a second sales/tax credit. It is selected automatically when the chosen order has Refund Due.

Example: $100 collected, revised order $75 → $25 Refund Due. Confirming the $25 payout leaves $75 net received, $0 owed and $0 Refund Due. Pending refund cheques reserve funds and clear the balance only after bank clearance. Amount caps, supporting documents, dates, review confirmation, stale-preview detection and duplicate-submission protection remain enforced.

Gateway refunds for revised orders cannot exceed current Refund Due. Helcim supports the existing partial-refund control. Other gateway screens that only support a full refund do not offer that action for a partial difference; perform the partial refund in the provider portal and reconcile it. Do not record the same provider payout as an additional external refund. Existing external payouts/credits/pending cheques block conflicting gateway requests.

## Deployment

Apply `2026_11_18_090000_add_delivered_order_revisions.php` through the normal `php8.4 artisan migrate --force` release step. It adds `order_revisions` and the refund `kind` field; existing refund rows default to the established return/credit behavior. No dependency or asset build is needed. Refresh cached configuration and views. Keep the normal scheduler running and verified outgoing email settings enabled. Check PHP GD before the document email outbox, as required by the shared document theme.

The local development database has the additive migration. These changes are included in the 18 November release patch. Live deployment remains a separate step; use the commands in `release-2026-11-18.md`.

## Verification

- Full regression suite: 639 tests, 13,325 assertions, no failures/errors; the one MySQL-only case skipped on SQLite passed separately (three assertions).
- MySQL registration/refund/amendment checks: 27 tests, 309 assertions, all passing, using a disposable database.
- Browser checks: Super Admin editor, automatic overpayment selection, zero additional tax credit, $25 payout preview ending at $0 owed/refund due, mobile layout and branded email preview; no JavaScript errors.
- Shared-theme adjustment PDFs checked at one and three pages; 45 PHP files passed syntax checks and all Blade views compiled. Local site returned HTTP 200 after migration. No live server changes, external emails or real payouts were made.
