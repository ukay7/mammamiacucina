# POS product suggestions

The desktop workspace uses two equal columns: customer selector and product browser on the left, recommendations and current sale on the right. Each column flows independently to avoid a gap below the customer selector. The recommendations contain two compact groups with up to three products each. On smaller screens the columns stack. Product photos open the existing details dialog, and each product has quantity controls and an Add button using the normal cart validation.

## Ranking

- **Customer favourites:** the selected customer's distinct completed orders containing a product, highest frequency first. Repeat lines in one order count once. Ties use the latest order date, total quantity, then product ID. History has no date cutoff.
- **Stock to move:** current tracked stock minus units sold over the last 90 Toronto calendar days, including today. The largest positive excess comes first, then current stock, then product ID. Stock must exceed its low-stock threshold. Each row shows current stock and recent sold quantity so the suggestion's basis is visible.

Both groups use delivered/completed orders, excluding deleted orders, future-dated orders and orders with a current sandbox payment. Only active products with a selling price and at least one available unit are eligible. Untracked stock is allowed for favourites, but cannot qualify for excess-stock suggestions. A monetary refund does not imply a physical return and does not subtract product quantities from demand.

Prices use the selected customer's individual/business pricing and product discounts. Suggestions are read-only: they do not reserve inventory, place orders, send emails or change the catalogue. Checkout retains its existing stock and price checks.

## Integration

- `GET /admin/pos/recommendations?customer_id=…` requires the existing `pos.manage` permission and validates the customer through `PosCustomer`.
- Changing customers immediately clears old recommendations. Late responses for the previous customer are discarded. Resuming a draft reloads current recommendations.
- Missing history or eligible stock produces an empty-state message. A failed recommendation request offers Retry without blocking normal product browsing.
- No migration or additional package is required for this enhancement. The other changes in the pending patch still require their listed migrations.

## Verification

`PosRecommendationsTest` covers completed-order frequency, customer isolation, stock ranking, the Toronto date boundary, exclusions, pricing/discounts, permissions and read-only behavior. Browser verification covers the 6/6 layout, both groups' quantity/Add controls, product details, customer switching, late responses and mobile overflow.
