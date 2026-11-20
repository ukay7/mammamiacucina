# Notifications

Manage Email → Notifications provides numbered campaigns (NT-00001), editable drafts, duplicate/archive, Select2 customer search and additional email tags, All customers, business/individual filtering, optional alternate addresses, plain-text composition with safe HTML preview, explicit single-address test sends, review/confirmation and immediate or scheduled bulk delivery in Toronto time.

Only email is available today. Campaigns, deliveries, events and suppressions carry channel/source data; `config/notifications.php` registers channel adapters. Future app/SMS adapters can share campaign and reporting concepts while adding their own validation, configuration and delivery policies. They are not enabled by this release.

## Sending and reports

1. Enable and successfully test the existing sender in **Email Configuration** (SMTP or Resend API).
2. Save a notification draft and send a test to your own address. Check its inbox/spam rendering. Tests never send to the bulk audience, alternate addresses, or the office copy address.
3. Review recipients, exclusions and schedule. Confirm within 15 minutes. Editing the draft or changing sender settings requires another test. The reviewed audience and message are frozen; later customer edits do not silently change the campaign.
4. The worker sends individually, deduplicates addresses, and rechecks unsubscribe/suppression state before dispatch. Pause/resume/cancel affect pending deliveries; an in-flight message can still finish.
5. Reports have recipient search, status/outcome/engagement filters, separate test history, per-recipient event timelines and CSV exports. Accepted means the provider accepted the request, not guaranteed inbox placement. Opens are estimates, affected by image blocking/privacy tools; link scanners also affect click counts. No recipient IP addresses or user agents are collected.

Bulk unsubscribe links use unguessable tokens, confirmation on GET and one-click POST support. Hard bounces and complaints from verified webhooks suppress future promotional sends. These preferences do not alter order confirmations, receipts, verification or password-reset messages. Test bounces never suppress customers. Send only to recipients you are authorized to contact.

## Unsubscribe, consent and recipient history

Open **Notifications → Recipients & consent** to search addresses, filter current subscription/provider-block status, view accepted campaign counts, last sent/unsubscribe dates and the originating campaign, or export the filtered list. Each email has its own consent timeline and email history, including test sends. Campaign reports also show current email preferences and the historical **Unsubscribed via this email** flag; resubscribing does not erase that history.

An unsubscribe is immediate and indefinite. It blocks both bulk notifications and single-recipient notification tests, suppresses queued/retry deliveries, and is checked again at provider dispatch. Links keep working without login or expiration even after the campaign is archived. A message already handed to the email provider cannot be recalled. Order receipts and account-security emails use the separate transactional system and continue; do not add promotions to those messages to bypass a withdrawal.

Preferences belong to a normalized email address, not a customer ID. Adding the address again, creating a customer, using an alternate address or duplicating a campaign never resets its opt-out. Alternate addresses have their own independent preferences. The migration preserves existing unsubscribe requests and provider suppressions. Existing addresses without recorded consent are labelled **Consent not recorded**, never automatically marked subscribed. This status is not proof of permission; before queuing, the sender must explicitly confirm valid permission to contact the eligible audience. This module does not determine implied-consent eligibility or import consent evidence from external systems automatically.

Only Super Admin or staff with **Notifications → Manage unsubscribe requests and record consent with evidence** can record a manual unsubscribe or consent. To resubscribe:

1. Open the email's **History & consent** page.
2. Enter the source and Toronto date/time of the recipient's fresh explicit consent, plus their actual request or detailed evidence description. Consent must not predate the latest unsubscribe or be in the future.
3. Optionally attach the email/request or other proof (the same five-file, 10 MB combined limits and formats as notification attachments).
4. Confirm the recipient explicitly requested subscription and save. A customer registration or order is not itself evidence of renewed promotional consent.

There is no unrestricted subscription toggle. Each action retains who recorded it, when, the source, consent date, written evidence and optional private files. Evidence text/file metadata is encrypted and file downloads require the consent-management permission; ordinary notification viewers see status/history without the private proof. There are no edit/delete actions for consent records. Files are retained in the same private attachment storage and must be included in backups; they are never attached to campaign emails. Do not purge the address registry when removing customer records or archiving campaigns, since the opt-out must survive.

Fresh consent permits newly prepared campaigns only. Old queued deliveries carry a consent version and cannot restart after resubscription; reviews prepared before a preference change must be refreshed. Duplicate submissions and stale admin forms cannot override a more recent unsubscribe. Independent bounce/complaint/provider blocks remain in effect even after consent is recorded.

Apply `2026_11_21_010000_add_notification_consent_history.php` using the normal `php8.4 artisan migrate --force` release step, then clear/rebuild caches before reopening the application. Pause the scheduler during the release or use the application's maintenance-mode deployment so workers do not run mixed code/schema versions. No new cron is required. Existing staff need the new consent permission explicitly assigned; Super Admin receives it automatically.

## Attachments

Create/edit drafts support up to **five files, 10 MB combined**, including files already saved. Supported formats are PDF, JPG/JPEG, PNG, WebP, DOC/DOCX and XLS/XLSX; the server validates both the extension and detected file type. Save file changes before testing. Removing or adding files invalidates the previous successful test, and a new test and review are required before queuing. File inputs must be selected again after a failed form submission.

The exact saved files are attached to the single test email and to every individually queued email through SMTP or Resend. Preview, confirmation, campaign reports and delivery timelines list filenames and sizes. Draft duplication preserves its attachments and requires a fresh test.

Files live in `storage/app/private/notification-attachments`, with random immutable names and encrypted metadata on the campaign/snapshots. Downloads require Notifications access and are scoped to the campaign or historical test version. Files use mode `0660` inside a shared private directory with mode `2770`: on web01, the PHP-FPM user and `mmcdeploy` scheduler share the `www-data` group. This lets both processes read uploaded files while preventing public filesystem access. Removing files from a draft or archiving a campaign retains their objects because other drafts, test history and queued sends may still reference them. Include this private directory in backups; do not purge files just because a draft was edited. No automatic attachment purge is provided.

Workers store only file metadata in recipient payloads, reading and encoding the bytes at send time. Size and SHA-256 checks stop a missing/modified attachment from being silently omitted. Such deliveries require review; an earlier failed provider attempt may already have sent, so check provider logs before resending. All application instances and scheduled workers must share this private storage.

Apply `2026_11_20_190000_add_notification_attachments.php` with the normal release migrations before using this update. For an existing notification installation, use `sudo -H -u mmcdeploy php8.4 artisan migrate --force` followed by `sudo -H -u mmcdeploy php8.4 artisan optimize:clear` from the website directory. The existing notification cron remains unchanged. PHP must allow at least 10 MB per file, a POST body larger than 10 MB (for multipart overhead), and at least five uploaded files; the existing 100 MB upload / 128 MB POST configuration is sufficient. The initial-install script below is not a substitute for subsequent release migrations.

## Live deployment (PuTTY, root)

```bash
cd /var/www/mammamiacucina-app
sudo -H -u mmcdeploy git pull --ff-only origin main
cd website
bash deploy-notifications.sh
sudo crontab -u mmcdeploy -l
```

The script checks PHP GD, installs locked PHP dependencies, applies the notification module, attachment and consent migrations, rebuilds configuration/views, checks the queue without sending, reloads PHP-FPM and enables cron. It preserves the existing mmcdeploy scheduler or adds it if missing. Existing application data is not reset. It does not create a database/storage backup. A failing deployment stays in maintenance mode with its error visible; fix the cause and rerun. Do not use `migrate:fresh` or reseed production.

The scheduler runs **once per minute**, as mmcdeploy:

```cron
* * * * * cd /var/www/mammamiacucina-app/website && /usr/bin/php8.4 artisan schedule:run >> /var/www/mammamiacucina-app/website/storage/logs/scheduler.log 2>&1
```

If the server has a separately managed scheduler in root's crontab or `/etc/cron.d`, retain only one MMC scheduler invocation. Do not add a second `notifications:send` cron; Laravel schedules it already. Ensure `APP_URL=https://mammamiacucina.ca` so tracking/unsubscribe links use the public domain. Keep `APP_KEY` stable: saved message bodies, reviews, snapshots and webhook secrets are encrypted with it.

## Resend delivery/bounce reporting (one-time setup)

SMTP supplies acceptance and our own open/click tracking, but no provider delivery/bounce webhook. For full delivery reporting with Resend:

1. Create a Resend webhook for `https://mammamiacucina.ca/notifications/webhooks/resend`.
2. Subscribe to `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced`, `email.complained`, `email.failed`, `email.suppressed`.
3. Copy its `whsec_...` signing secret into **Notifications → Settings**. It is encrypted and never shown again. Alternatively set `NOTIFICATIONS_RESEND_WEBHOOK_SECRET` in `.env` and rebuild configuration; a saved database secret takes precedence.
4. Send your test, then a small confirmed campaign to your own subscribed address. Its report should show a provider event. The settings page reports the last verified webhook.

Webhook requests verify the raw payload's Svix HMAC signature and timestamp, deduplicate event IDs, retain unmatched events for seven days to reconcile send-response races, and prevent late events from overwriting a more serious outcome. Provider open/click webhooks are ignored to avoid double-counting our own tracking.

References: [Resend event types](https://resend.com/docs/webhooks/event-types), [Resend webhook verification](https://resend.com/docs/webhooks/verify-webhooks-requests), [Svix manual verification](https://docs.svix.com/receiving/verifying-payloads/how-manual).

## Operations

```bash
cd /var/www/mammamiacucina-app/website
sudo -H -u mmcdeploy php8.4 artisan notifications:send --dry-run
sudo -H -u mmcdeploy php8.4 artisan schedule:list
sudo systemctl status cron --no-pager
tail -n 50 storage/logs/scheduler.log
```

An authorized manual batch uses `sudo -H -u mmcdeploy php8.4 artisan notifications:send --limit=20` and **does send due messages**. Default batch size is 20 (configurable 1–100). Each batch is time-bounded, rate-spaced and protected by a cache lock plus database claim locks. Keep all workers on the same shared cache/database; the current single-server deployment uses the existing cache configuration. No daemon or `queue:work` is needed.

Resend retries preserve the exact encrypted payload and idempotency key, use exponential backoff, and stop after five attempts or 12 hours. SMTP uncertain failures are not automatically retried, avoiding duplicate mail. `Needs review` means check the provider logs before preparing another tested campaign. Sender credential/revision changes stop old queued deliveries for review. A stale worker notice appears in the UI after five minutes.

Staff access is grouped under Notifications with separate view, manage drafts, send/control queue, configure, and consent-management permissions. Super Admin has access automatically.

## Validation

Feature tests use isolated SQLite and mocked providers; they never send external email. Coverage includes deduplication, alternate addresses, suppressions, sender/draft revisions, review expiry, idempotency, privacy, SMTP multipart messages, retries, leases, cancellation races, signature verification, webhook races, tracking, CSV injection escaping and authorization. Browser checks use a separate fictional database with all provider requests mocked. Real inbox delivery and webhook setup must be checked against the live provider after deployment.

Select2 4.0.13 is bundled locally under its MIT license in `public/admin-assets/vendor/select2`.
