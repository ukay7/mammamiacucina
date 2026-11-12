# Notifications

Manage Email → Notifications provides numbered campaigns (NT-00001), editable drafts, duplicate/archive, Select2 customer search and additional email tags, All customers, business/individual filtering, optional alternate addresses, plain-text composition with safe HTML preview, explicit single-address test sends, review/confirmation and immediate or scheduled bulk delivery in Toronto time.

Only email is available today. Campaigns, deliveries, events and suppressions carry channel/source data; `config/notifications.php` registers channel adapters. Future app/SMS adapters can share campaign and reporting concepts while adding their own validation, configuration and delivery policies. They are not enabled by this release.

## Sending and reports

1. Enable and successfully test the existing sender in **Email Configuration** (SMTP or Resend API).
2. Save a notification draft and send a test to your own address. Check its inbox/spam rendering. Tests never send to the bulk audience, alternate addresses, or the office copy address.
3. Review recipients, exclusions and schedule. Confirm within 15 minutes. Editing the draft or changing sender settings requires another test. The reviewed audience and message are frozen; later customer edits do not silently change the campaign.
4. The worker sends individually, deduplicates addresses, and rechecks unsubscribe/suppression state before dispatch. Pause/resume/cancel affect pending deliveries; an in-flight message can still finish.
5. Reports have recipient search, status/outcome/engagement filters, separate test history, per-recipient event timelines and CSV exports. Accepted means the provider accepted the request, not guaranteed inbox placement. Opens are estimates, affected by image blocking/privacy tools; link scanners also affect click counts. No recipient IP addresses or user agents are collected.

Bulk unsubscribe links use unguessable tokens, confirmation on GET and one-click POST support. Hard bounces and complaints from verified webhooks suppress future bulk sends. These preferences do not alter order confirmations, receipts, verification or password-reset messages. Test bounces never suppress customers. Send only to recipients you are authorized to contact.

## Live deployment (PuTTY, root)

```bash
cd /var/www/mammamiacucina-app
sudo -H -u mmcdeploy git pull --ff-only origin main
cd website
bash deploy-notifications.sh
sudo crontab -u mmcdeploy -l
```

The script checks PHP GD, installs locked PHP dependencies, migrates only the six new notification tables, rebuilds configuration/views, checks the queue without sending, reloads PHP-FPM and enables cron. It preserves the existing mmcdeploy scheduler or adds it if missing. Existing application data is not reset. It does not create a database/storage backup. A failing deployment stays in maintenance mode with its error visible; fix the cause and rerun. Do not use `migrate:fresh` or reseed production.

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

Staff access is grouped under Notifications with separate view, manage drafts, send/control queue, and configure permissions. Super Admin has access automatically.

## Validation

Feature tests use isolated SQLite and mocked providers; they never send external email. Coverage includes deduplication, alternate addresses, suppressions, sender/draft revisions, review expiry, idempotency, privacy, SMTP multipart messages, retries, leases, cancellation races, signature verification, webhook races, tracking, CSV injection escaping and authorization. Browser checks use a separate fictional database with all provider requests mocked. Real inbox delivery and webhook setup must be checked against the live provider after deployment.

Select2 4.0.13 is bundled locally under its MIT license in `public/admin-assets/vendor/select2`.
