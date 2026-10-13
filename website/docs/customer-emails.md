# Customer lifecycle emails

Registration, invitation, resend and password reset continue through the existing email service and verification cooldown. Business registration/invitations use the new review-and-verification template. Approval and verification remain independent gates; the active email is queued only after both gates pass.

Website/POS order confirmations, business approval/activation, customer-facing order transitions, and verified payment collections are stored in a transactional outbox in `email_history`. No historical orders are backfilled. Orders without an email address cannot receive notifications. Pending receipt uploads are never payment confirmations. Partial collections each get a receipt showing the remaining balance. Internal notes and warehouse issues do not notify customers.

PDFs contain immutable snapshots of items, money and fulfillment at the event. Templates are editable under Manage Email > Email Templates. Existing customized templates are preserved by migration. Queued messages keep their original rendered text; edits apply to subsequent messages. Sandbox order emails are explicitly labelled TEST ORDER.

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
3. Place a cash or e-transfer order. Confirm one order email with an unpaid PDF and correct items/totals/address. Reopening success must not create another confirmation.
4. Upload an e-transfer receipt: no paid email. Record a verified partial collection: payment email with amount due. Record the remaining collection: a further receipt plus warehouse status update.
5. Change a customer-facing order status; confirm one update. Save unchanged or edit an internal note: no update email.
6. Use a separate Helcim developer account on UAT for card success/decline tests. A verified success produces one receipt, including when reconciliation runs again. Do not use real cards for sandbox testing.

Automated checks fake outbound requests and do not prove production DNS, credentials or inbox delivery. Existing league/commonmark dependency advisories reported by Composer audit are unrelated to these plain-text emails and Dompdf attachments and remain a separate dependency maintenance item.
