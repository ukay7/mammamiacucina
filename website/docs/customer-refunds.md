# Customer refunds

Super Admin can open **Customer profile → Refunds → Add Refund**, or **Refund** on a delivered/completed order. The order number is mandatory and is preselected from an order. Orders must belong to that customer, have confirmed totals and use live CAD funds.

The form records an external payout. It does not send money or call a payment gateway. Cash, e-transfer, cleared external terminal/card and external PayPal payments require confirmation that the money was actually returned. Non-cash methods require a transaction reference and supporting JPG/PNG/WebP/PDF (5 MB each; 20 MB combined). Up to ten methods can share one refund. Check the included GST/HST against the refunded items; the proportional suggestion is editable.

Refunds have an RF number. Every confirmed payout creates a separate CN credit note and a negative refund settlement in one database transaction. Original order items, delivery status, stock and invoice snapshots stay intact. For a fully paid $100 order, a $30 refund means $70 net received, $30 credit and $0 due. A partially paid order retains its existing amount due.

Outgoing cheques reserve the amount but post neither a credit nor a cash movement until Super Admin records bank clearance. The refund detail page offers reviewed **Confirm bank clearance** and **Cancel pending cheque** actions. Cancellation releases the reservation. Cleared/confirmed entries cannot be edited or deleted. A cancellation of a pending cheque is not a reversal of money already returned.

Limits are rechecked under customer and order row locks. Previous confirmed refunds, reversals and pending cheques reduce available funds. A signed preview binds the actor, order balance, evidence hash and entered details; stale previews fail. Each request UUID can create only one refund. Duplicate non-cash references for the same customer/method are rejected. Customer/profile and order pages display the history; customer Documents also includes refund records and credit notes, with ownership enforced.

For orders with an integrated gateway payment, this module supports an actual external cash/e-transfer/cheque payout. It rejects an external-terminal card/PayPal entry for an integrated payment to prevent counting the same provider refund twice. Pending/review/sandbox gateway payments block new refunds. Existing gateway refund actions also block once this module has credited or reserved money; reconcile before any further provider payout. Provider webhooks still record real provider activity.

Original order value remains visible in reports, alongside credit notes and adjusted value. Collections & Refunds includes only confirmed refund cash movements. Pending cheques do not count as money returned. Printing an old refund action preserves that action's figures. Credit notes use saved company/customer details and the shared A4 theme, including GST/HST at the bottom right.

## Deployment

Deploy the code and run `php8.4 artisan migrate --force` before serving pages that read the new ledger columns. The migration only adds tables/columns, with zero defaults for existing orders. Enable PHP GD for PDF logos. No queue worker or cron is required for this module; no refund notification emails are sent automatically.

For the existing PHP 8.4 live installation, run this in PuTTY as root. Maintenance starts before the pull so the new code cannot query missing refund columns. The subshell stops on a failed command; resolve any error before bringing the site back online.

```bash
(
  set -e
  cd /var/www/mammamiacucina-app/website
  sudo -H -u mmcdeploy php8.4 artisan down --retry=60
  sudo -H -u mmcdeploy git pull --ff-only origin main
  sudo -H -u mmcdeploy composer dump-autoload --no-dev --optimize
  sudo -H -u mmcdeploy php8.4 artisan config:clear
  sudo -H -u mmcdeploy php8.4 artisan route:clear
  sudo -H -u mmcdeploy php8.4 artisan migrate --path=database/migrations/2026_11_14_120000_create_customer_refunds.php --force
  sudo -H -u mmcdeploy php8.4 artisan config:cache
  sudo -H -u mmcdeploy php8.4 artisan view:cache
  sudo systemctl reload php8.4-fpm
  sudo -H -u mmcdeploy php8.4 artisan up
  sudo -H -u mmcdeploy git log -1 --oneline
)
```

After deployment, sign in as Super Admin and open Customer profile → Refunds, or Refund on a delivered/completed order. Review the form and order selection without recording a real payout just to test it.

Do not roll back the schema after recording real refunds: the credit and payout records are part of the financial history. Resolve business corrections through reviewed financial entries rather than deleting history.

## Verification

`php artisan test --filter=CustomerRefundTest` covers balanced credits, partial/full refunds, cheque reservations and clearance, stale previews, repeated requests, evidence tampering, permissions, documents and gateway conflicts. Run the same suite on a dedicated disposable MySQL database as well as SQLite. Never point a `RefreshDatabase` test configuration at a development or live customer database.
