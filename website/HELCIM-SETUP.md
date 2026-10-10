# Helcim card payments

Helcim is disabled by default. No existing customers, products, cash orders or e-transfer orders are changed by the migration. Stripe/PayPal credentials remain available to reconcile older orders.

## Account setup

1. Obtain a Helcim **developer test account**. UAT must use a token from that account. Helcim uses the same API URL for test and production; selecting UAT in our admin does not make a production token safe for testing.
2. In Helcim API Access, enable the checkout integration option and allow the website's exact domain. Configure transaction processing access for purchases and refunds, plus transaction read access. Our connection test checks both authentication and reading transactions without making a payment.
3. In Helcim Webhooks, enable **Card Transaction** events. Set the UAT destination to `https://YOUR-HOST/payments/card-events?mode=sandbox` and copy its verifier token. For production use `?mode=live`. This route deliberately does not contain “Helcim”, which the provider forbids in webhook URLs.
4. In admin → Gateway Settings, save the API token and webhook verifier token under Helcim's UAT profile. Select UAT and enable Helcim. Use **Test saved credentials**. Blank secret fields retain saved values; secrets are encrypted and never displayed.
5. Local browser confirmation and reconciliation work with the test API. Incoming webhooks require a public HTTPS URL forwarding to the local server; alternatively test webhooks on an HTTPS staging deployment. Test the webhook separately before production.
6. Use the official test cards from the developer account documentation. Never use test card numbers against a live token.

## Server deployment

Deploy the tested commit through the existing deployment script. It backs up the database/storage and runs migrations. Do not run any reset or demo-data command for this integration.

```bash
cd /var/www/mammamiacucina-app
sudo -H -u mmcdeploy git pull --ff-only origin main
bash website/deploy-update.sh
```

Ensure outbound HTTPS (443) to `api.helcim.com` and customer browser access to `secure.helcim.app`. SMTP ports are unrelated.

The existing Laravel scheduler must run every minute as `mmcdeploy`. Check `sudo crontab -u mmcdeploy -l`; if no scheduler entry exists, add this through `sudo crontab -u mmcdeploy -e` (do not duplicate it):

```cron
* * * * * cd /var/www/mammamiacucina-app/website && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

Manual reconciliation:

```bash
cd /var/www/mammamiacucina-app/website
sudo -H -u mmcdeploy php8.4 artisan payments:reconcile
```

## Expected flow / acceptance tests

- Choose an individual or approved business customer, add products, choose pickup or delivery, then Pay by Card. The server uses the customer's existing price rules, tax and delivery quote.
- Submitting checkout reserves stock once and creates one `awaiting_payment` order. Re-submitting the same checkout token returns that order. Resume uses the same Helcim session, never a new payment session for that order.
- Card details stay in Helcim's iframe. Amount/currency and itemized invoice are set by our server. The browser sends only a transaction ID for confirmation; the server independently retrieves and reconciles the invoice's transactions.
- An approved matching CAD purchase marks the order `paid` and `warehouse_pending`, once. Invoice/print and Payments show the reference and original positive collection. No duplicate settlement row is added because the original gateway payment already supplies that credit.
- Declined, forged, wrong-order, wrong-amount or unconfirmed transactions cannot mark the order paid. A network timeout is not proof of failure. Check status instead of paying again.
- Close the browser after paying: the signed webhook or scheduled reconciliation must still mark the order paid. My Orders provides a resume/check-payment link.
- Cancel an unpaid order: Helcim has no modal cancellation endpoint. We disable reopening locally, wait until the token's 60-minute lifetime plus 10-minute grace has elapsed, and query transactions before releasing stock. During an API outage stock remains reserved. A subsequently confirmed late payment goes to `payment_review` rather than warehouse.
- Under Orders → Payments, an authorized admin can request a full or partial gateway refund. This moves money in production. Only confirmed refunds reduce the ledger. A stale refund form is rejected, repeated requests use an idempotency key, and reconciliation recovers a successful provider refund after a response timeout.
- Full refunds of unfulfilled orders cancel/restock once. Refunds of dispatched/completed orders do not imply a physical return and do not restock automatically.
- A second approved purchase or unexpected transaction type requires administrator review. Do not fulfill an order marked `payment_review` until resolved.
- Check both pickup/delivery, both pricing types, receipt print, payment report's sandbox filter, role permissions and mobile checkout. Cash and e-transfer should behave as before.

Only after developer-account acceptance testing, enter separate production credentials and webhook verifier, whitelist the production domain, test API connectivity, and enable Production. Preserve old credentials for reconciliation/refunds on existing orders. Automated mocked tests do not replace this account-level acceptance test.

## References

- https://devdocs.helcim.com/docs/developer-testing
- https://devdocs.helcim.com/docs/initialize-helcimpayjs
- https://devdocs.helcim.com/docs/render-helcimpayjs
- https://devdocs.helcim.com/reference/getcardtransactions
- https://devdocs.helcim.com/reference/refund
- https://devdocs.helcim.com/docs/webhooks
