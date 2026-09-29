# Product pricing
The product editor retains Identity, Packing and handling, Images and ingredients, Pricing, and Notes and sources.
New Pricing and approval screens are removed. Extracted Source Data is hidden; existing source metadata is retained.

## Save a price
1. Enter and verify supplier price/basis, currency, discounts, exchange rate, freight, carton quantity and markup or margin.
2. Calculate Preview. This does not write or publish anything.
3. Choose the calculated per-piece or per-carton amount for the current store selling unit.
4. Save Price directly updates Total Selling Price (CAD), used by website and POS. No draft submission or approval is required.

Identity includes an editable Total Selling Price (CAD). Save Product saves this price directly along with metadata/media; calculation is optional. Save Price saves pricing separately and refreshes the displayed selling price and editor revision.
For new products, save the product first.

## Discount and formulas
Use Original Source Discount copies the original supplier expression into Supplier discount.
35+3 applies 35%, then 3% to the remainder: effective discount 36.95%.
Quotes are normalized to a piece using supplier price basis (piece, carton, pack or kilogram).
Discounted EUR per piece is converted to CAD. Freight per carton / pieces per carton and other cost per piece are added.
Markup: landed cost * (1 + percentage / 100).
Margin: landed cost / (1 - percentage / 100), below 100%.
Round selling price per piece to cents before multiplying by carton quantity.

## Preservation
No products, categories, historical orders, stock counts or existing source metadata are deleted.
Saving a carton amount does not convert inventory quantities; choose the amount matching the existing store unit.
Existing draft/settings and audit tables are retained for compatibility. Saved settings are reused; each direct save records a snapshot with status saved. Old pending proposals cannot be approved through retired routes.
Stale product/pricing revisions are rejected. Invalid calculations cannot save.
Spreadsheet imports cannot overwrite prices managed through the Pricing tab; use the direct price field or Save Price.
Super Admin and users with products.manage can save prices. Separate approval permission is no longer used.

## Deployment
Local changes only until deployment is requested. Existing additive product DNA migration remains necessary on servers that have not received it. No new migration is required for this simplification. Do not reset or replace the production database.

## Product table columns
Choose columns offers 39 fields and Identity, Pricing, Missing data, Name and price, All columns and Clear selection presets. Selections persist per admin in the browser and in filter/pagination links. Excel, PDF and Print export the selected columns for all matching products. Missing data is a column preset, not a row filter. Calculated price columns use saved calculator inputs and are separate from the current published price. Excel exports private image links rather than embedded images. The table scrolls horizontally and vertically while keeping headers and actions accessible.
