# Customer lifecycle emails

Registration, invitation, resend and password reset continue through the existing email service and verification cooldown. Business registration/invitations use the new review-and-verification template. Approval and verification remain independent gates; the active email is queued only after both gates pass.

Website/POS order confirmations, office new-order alerts, business approval/activation, customer-facing order transitions, and verified payment collections are stored in a transactional outbox in `email_history`. No historical orders are backfilled. Orders without a customer email address still notify the office, but cannot notify the customer. Pending receipt uploads are never payment confirmations. Partial collections each get a receipt showing the remaining balance. Internal notes and warehouse issues do not notify customers.

PDFs contain immutable snapshots of items, money and fulfillment at the event. Templates are editable under Manage Email > Email Templates. Existing customized templates are preserved by migration. Queued messages keep their original rendered text; edits apply to subsequent messages. Sandbox order emails are explicitly labelled TEST ORDER.

## Office new-order alerts

Every newly placed website or admin/staff Quick Sale/POS order queues a separate `new_order_notification` email to `info@mammamiacucina.ca` (`mail.order_notification_address`). It includes the order PDF, customer, order source, placing user, payment status, balance and a link requiring staff sign-in. Pending/unpaid and sandbox orders are clearly identified. The office alert is created in the order transaction; a rolled-back order does not send an alert. Repeated confirmations reuse `order-office:{id}` so they cannot create duplicate office notifications.

Office alerts have their own Email History entry and retries, use the existing SMTP/Resend outbox, and never copy a customer's alternate address. They trigger when an order is first placed; later status changes and payments retain their existing customer notification behavior. The migration `2026_11_12_130000_add_office_order_email_template` adds the editable office template without overwriting customization or notifying on historical orders. Apply it before serving the updated code. `OfficeOrderEmailTest` checks both delivery providers, transactional rollback, duplicate protection and immutable retry attachments without sending live mail.

## Alternate customer email

Customers can add, change or clear an optional alternate email in My Profile. Authorized staff can do the same in Edit Customer or the customer detail editor. The address is validated, trimmed and stored in lowercase on the customer record; the primary login email and its verification state are unchanged. The existing business contact email remains separate and is not automatically a delivery recipient.

Both the immediate email service and customer outbox copy ordinary customer notifications to the alternate address. This includes website/POS order confirmations, status updates, verified payment receipts, manually queued invoices and business-account activation. Both addresses receive the same message and any attachment. Blank, invalid or case-insensitively identical alternate addresses do not add a recipient. Order emails resolve the alternate from the order's customer, including when the order retains an older primary address; sends without a customer reference may match a single customer by primary email only.

Verification, invitations, password resets and business-approval messages containing a verification link go only to the primary email. An alternate cannot be used to log in, verify the primary email or request a password reset. SMTP configuration tests go only to their explicitly entered recipient.

Email History displays and searches both recipients. Each queued event saves its alternate recipient at creation, preserving the same recipients, message, PDF and idempotency key across retries. Profile changes apply to newly created messages; already queued messages retain their saved recipients. Historical emails and existing queued messages are not backfilled or resent.

Deployment requires the additive migration `2026_11_12_120000_add_customer_alternate_email` before serving the changed code or resuming email delivery. It adds nullable `customers.alternate_email` and `email_history.alternate_recipient` fields without rewriting account, order, invoice or payment data. Keep PHP GD enabled for PDF logos. Run `CustomerAlternateEmailTest` alongside the existing customer-account and email regression tests; delivery tests use fake providers and do not send real messages.

## Deployment

Run the existing `deploy-update.sh` after pulling main. Composer installs the locked Dompdf dependency, migrations add outbox fields/templates and the fulfillment date used by reports. No email credentials need re-entry. Email settings must be enabled and successfully tested for their current revision.

The existing mmcdeploy cron running `artisan schedule:run` every minute now also runs `emails:deliver`. No second cron or persistent queue worker is necessary. Check:

```sh
sudo crontab -u mmcdeploy -l
cd /var/www/mammamiacucina-app/website
sudo -H -u mmcdeploy php8.4 artisan schedule:list
sudo -H -u mmcdeploy php8.4 artisan emails:deliver
```

The command drains up to 20 messages per run, stopping after approximately 45 seconds plus the current request. Email History shows queued, sending, retry, accepted or needs-review. Accepted means provider acceptance, not guaranteed inbox delivery; use Resend delivery logs and the recipient inbox to confirm.

Event keys and locked claims prevent duplicate dispatch from repeated saves/callbacks. Resend retries keep the identical payload and UUID idempotency key, with at most five attempts within 12 hours of creation. Uncertain SMTP outcomes, changed credentials, expired retries and exhausted retries require review instead of blind resending. Never resend a needs-review entry until the provider logs confirm whether it was accepted. Private queued bodies/PDFs are encrypted and removed after acceptance. No API errors or keys appear in Email History.

## Live smoke test

Email History now offers **View Email** for new delivery attempts, with the saved plain-text message and the exact PDF attachment where present. Copies are encrypted in the database and excluded from model JSON and history list queries. Verification/password-reset links are redacted in the copy only. The separate original outbox payload still clears after acceptance; retries use that original payload and the same idempotency key. Viewing/downloading requires `settings.manage`, uses no-store responses, and never sends another email. Failed or logged-only attempts may have a saved copy but are clearly labelled, not claimed as sent. Older accepted emails cannot be reconstructed. The archive migration does not backfill or send emails. Customer cleanup deletes these archives with their email-history rows. Retained PDF copies increase database and backup size over time.

1. Register an individual using a mailbox you control. Verify the link and confirm shopping access; test password reset.
2. Register a business. Confirm the review/verification message; verify the email and check that purchasing still needs approval. Approve in admin and confirm one active email. Also test approval before verification with a separate account.
3. Place a cash or e-transfer order through both website checkout and staff Quick Sale. Confirm a customer order email (also to the alternate if configured) and a separate office alert to `info@mammamiacucina.ca`, with an unpaid PDF and correct items/totals/address. Reopening success must not create another confirmation or office alert.
4. Upload an e-transfer receipt: no paid email. Record a verified partial collection: payment email with amount due. Record the remaining collection: a further receipt plus warehouse status update.
5. Change a customer-facing order status; confirm one update. Save unchanged or edit an internal note: no update email.
6. Use a separate Helcim developer account on UAT for card success/decline tests. A verified success produces one receipt, including when reconciliation runs again. Do not use real cards for sandbox testing.

Automated checks fake outbound requests and do not prove production DNS, credentials or inbox delivery. Existing league/commonmark dependency advisories reported by Composer audit are unrelated to these plain-text emails and Dompdf attachments and remain a separate dependency maintenance item.
