<?php

return [
    'headers' => [
        'Status', 'Image', 'Internal code', 'Mamma Mia name', 'Supplier', 'Supplier SKU',
        'Product name on source', 'Manufacturer', 'Purchaser on document', 'Delivery destination on source',
        'Internal brand allocation', 'Category', 'Gluten-free status', 'Weight per piece, g',
        'Pieces per carton', 'Net carton weight, kg', 'Sell as', 'Minimum sale', 'Supplier quoted price',
        'Supplier price basis', 'Purchase currency', 'Price per piece, EUR', 'Discount %', 'After discount, EUR',
        'CAD per 1 EUR', 'Product cost, CAD / piece', 'Freight, CAD / carton', 'Freight, CAD / piece',
        'Other cost, CAD / piece', 'Total unit cost, CAD', 'Pricing method', 'Profit setting %',
        'Selling price / piece, CAD', 'Selling price / carton, CAD', 'Calculated margin %',
        'Image status', 'Ingredients file', 'Review notes',
    ],
    'ignored' => ['Status', 'Image', 'Image status', 'Ingredients file'],
    'columns' => [
        'Internal code' => 'qr_code', 'Mamma Mia name' => 'premium_marketing_name',
        'Supplier' => 'supplier', 'Supplier SKU' => 'product_code', 'Product name on source' => 'original_description',
        'Weight per piece, g' => 'unit_weight_g', 'Pieces per carton' => 'pieces_per_pack',
        'Net carton weight, kg' => 'pack_weight_kg', 'Price per piece, EUR' => 'supplier_unit_price_eur',
        'After discount, EUR' => 'supplier_unit_price_eur_2', 'CAD per 1 EUR' => 'rate_exchange_cad',
        'Freight, CAD / piece' => 'shipping_cost_cad', 'Other cost, CAD / piece' => 'surcharge_increase_cad',
    ],
    'dna' => [
        'Manufacturer' => 'manufacturer', 'Purchaser on document' => 'recipient',
        'Delivery destination on source' => 'delivery_to', 'Internal brand allocation' => 'allocated_to',
        'Category' => 'product_family', 'Review notes' => 'owner_notes',
        'Pieces per carton' => 'pieces_per_carton', 'Net carton weight, kg' => 'net_carton_kg',
    ],
    'inputs' => [
        'Supplier quoted price' => 'purchase_price', 'Supplier price basis' => 'purchase_basis',
        'Purchase currency' => 'currency', 'Pieces per carton' => 'pieces_per_carton',
        'Weight per piece, g' => 'grams', 'Discount %' => 'discount', 'CAD per 1 EUR' => 'fx',
        'Freight, CAD / carton' => 'freight_carton', 'Other cost, CAD / piece' => 'other_unit_cost',
        'Profit setting %' => 'business_profit_percent',
    ],
];
