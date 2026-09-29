# Stripe, PayPal and cash payments

Online checkout is implemented but disabled until credentials and webhooks are configured. Existing orders, products, categories, banners and uploads are preserved. The migration only creates payment tables. Do not run migrate:fresh, db:wipe, or production seeders.

## How payments work

Website checkout offers hosted Stripe card checkout, hosted PayPal approval, or cash on delivery. No card number, expiry, CVC, saved card or reusable payment authorization is stored by this application. Providers process the card details. The database keeps amounts, status and gateway transaction references.

The server calculates CAD totals from product Total Selling Price, configured delivery and tax. Stock is reserved once when the order is created. Redirects alone never mark an order paid: the server verifies the payment amount, currency and provider reference. Stripe notifications are signature checked; PayPal notifications are verified through PayPal.

Unpaid cancelled/expired checkouts release tracked stock after reconciliation. Provider outages keep stock reserved rather than assuming payment failed. Late payment after release is flagged for staff review, never automatically fulfilled. Repeated callbacks cannot deduct or restore stock twice.

Both website and POS orders remain in the existing Orders module. POS card entries still mean money collected through an external card terminal; they do not charge a card through Stripe. Hosted gateway checkout is the website payment flow.

## Gateway Settings screen

Open Admin → Settings → Gateway Settings. Super Admin has access; other roles need gateways.manage.

- Choose UAT / Sandbox or Production for new online checkouts.
- Enable Stripe and/or PayPal independently. Cash stays available.
- Expand each provider's UAT and Production sections and enter its credentials.
- Blank fields keep the saved value. Existing credentials are never sent back to the browser.
- Save, then use Test saved credentials to verify API authentication without charging a customer. A successful API check does not verify webhook delivery.
- Copy the environment-specific webhook URL shown for each profile, including ?mode=sandbox or ?mode=live. This lets older orders continue receiving notifications after switching modes.
- Configure the scheduler described below and complete provider sandbox transactions.

Credentials are encrypted in the database using Laravel's APP_KEY. Preserve that key and restrict database/.env backups. After the first save, this screen controls settings; .env payment values are only a fallback before a settings record exists. Existing fallback credentials are imported into their original environment when first saved.

Keep both profiles configured for the same merchant accounts used for those orders. Disabling a gateway prevents new orders but does not stop reconciliation/refunds of existing ones. Switching modes does not change existing orders' environments.

## Optional initial .env configuration

You can instead use the screen above. Before the first screen save, these website/.env values are supported for backward compatibility. Keep secrets out of Git and screenshots.

    PAYMENTS_MODE=sandbox
    STRIPE_ENABLED=true
    STRIPE_SECRET_KEY=sk_test_...
    STRIPE_WEBHOOK_SECRET=whsec_...
    PAYPAL_ENABLED=true
    PAYPAL_CLIENT_ID=...
    PAYPAL_CLIENT_SECRET=...
    PAYPAL_WEBHOOK_ID=...

Run php artisan config:clear locally, or rebuild production configuration with php8.4 artisan config:cache. A provider can stay disabled while configuring the other. Use APP_URL matching the real browser origin; use HTTPS outside localhost.

Stripe:
- Create a Stripe sandbox/test API key.
- Configure POST https://YOUR-HOST/payments/webhooks/stripe?mode=sandbox.
- Subscribe to checkout.session.completed, checkout.session.expired, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, charge.refunded and refund.updated.
- Set that endpoint's signing secret. For local testing use Stripe CLI: stripe listen --forward-to http://127.0.0.1:18088/payments/webhooks/stripe and use the CLI signing secret.
- Use Stripe test cards only in sandbox.

PayPal:
- Create a sandbox REST app linked to a sandbox Business account.
- Use a separate sandbox Personal buyer account for checkout.
- Configure POST https://YOUR-HTTPS-HOST/payments/webhooks/paypal?mode=sandbox and copy its webhook ID.
- Subscribe to CHECKOUT.ORDER.APPROVED, PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.PENDING, PAYMENT.CAPTURE.DENIED and PAYMENT.CAPTURE.REFUNDED.
- PayPal needs a public HTTPS callback URL; localhost requires a development tunnel configured with the same APP_URL.

Reference documentation:
- https://docs.stripe.com/payments/checkout
- https://docs.stripe.com/testing
- https://developer.paypal.com/api/orders/v2/
- https://developer.paypal.com/tools/sandbox/

## Scheduler (required)

Run the Laravel scheduler every minute to reconcile abandoned checkouts and release confirmed expired stock. Locally keep php artisan schedule:work running.

On the existing Linux server, configure ONE scheduler entry, as mmcdeploy:

    * * * * * cd /var/www/mammamiacucina-app/website && /usr/bin/php8.4 artisan schedule:run >> /var/www/mammamiacucina-app/website/storage/logs/scheduler.log 2>&1

Check the existing crontab before adding this. The deployment script does not overwrite server crontabs. You can also run php8.4 artisan payments:reconcile manually. Failed reconciliations are flagged in the order payment panel.

## Admin, receipts and reports

Orders display the payment method and status. Gateway orders lock payment amounts/status against manual edits. Staff with orders.manage can reconcile or cancel unpaid checkout; refunds additionally require payments.refund. Super Admin has access.

The refund action sends the remaining full refund to the provider and requires confirmation. Full refunds of unfulfilled orders restore reserved stock once. Refunds after dispatch or delivery do not mean goods were physically returned: inspect returns and adjust inventory separately. Partial refunds initiated in provider dashboards are reflected by notifications; reconcile/check the provider before further actions if a notification is delayed.

Payment Report supports date, method, source and status filters, with CSV export and separate Cash/Card/PayPal totals. Sandbox online payments are excluded by default. This is a report of orders created in the selected period and their current collection/refund state, not a bank settlement or fee accounting report. POS external-terminal card transactions are identified separately from Stripe.

Receipts show method, payment status, gateway reference, environment and refunded amount for gateway orders.

## Before switching live

1. Complete real sandbox tests for BOTH providers: successful payment, decline, cancel, closing the browser before return, duplicate notifications, expired checkout and refund. Automated tests mock provider APIs and do not replace these.
2. Resolve outstanding sandbox checkouts before changing modes. Keep test stock separate or restore it appropriately; successful sandbox orders reserve stock like real orders.
3. Client completes Stripe/PayPal Business account verification and enters LIVE credentials in the Production profile on Gateway Settings.
4. Create LIVE webhook endpoints on the production HTTPS domain using the displayed ?mode=live URLs, and save their live secrets/IDs in Gateway Settings.
5. Select Production and enable the required providers in Gateway Settings. Ensure APP_URL is correct, deploy additive migrations, and ensure the scheduler is running.
6. Verify webhook deliveries, place an authorized small real order and verify its refund/report entries.

Do not change APP_KEY or delete existing data. Changing keys alone is insufficient: mode, live webhooks, HTTPS and scheduler must also be configured.
