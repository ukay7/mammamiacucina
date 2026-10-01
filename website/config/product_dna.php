<?php

return [
    'identity' => [
        'manufacturer' => ['Manufacturer', 'text'], 'recipient' => ['Purchaser on document', 'text'], 'delivery_to' => ['Delivery destination on source', 'text'], 'allocated_to' => ['Internal brand allocation', 'text'], 'product_family' => ['Product family', 'text'],
        'gluten_status' => ['Gluten-free status', 'select', ['unknown' => 'Unknown', 'declared' => 'Declared — verify', 'verified' => 'Verified by owner', 'no' => 'No']],
        'supplier_confirmed' => ['Supplier confirms these product details', 'checkbox'],
    ],
    'packing' => [
        'weight_status' => ['Weight evidence', 'select', ['missing' => 'Missing', 'source' => 'Source record', 'working' => 'Working value', 'family' => 'Family reference', 'web' => 'Website reference', 'broker' => 'Broker statement', 'verified' => 'Verified', 'conflict' => 'Conflicting sources']],
        'pieces_per_carton' => ['Pieces per carton', 'integer'], 'carton_status' => ['Carton verification', 'select', ['missing' => 'Missing', 'source' => 'Source record', 'working' => 'Working value', 'family' => 'Family reference', 'verified' => 'Verified', 'conflict' => 'Conflicting sources']],
        'net_carton_kg' => ['Net carton weight (kg)', 'number'], 'sale_unit' => ['Sell as (planning only)', 'select', ['piece' => 'Piece', 'carton' => 'Carton', 'pack' => 'Pack', 'kg' => 'Kilogram']],
        'minimum_cartons' => ['Minimum cartons per order (planning)', 'integer'], 'minimum_pieces' => ['Minimum pieces (planning)', 'integer'], 'country_of_origin' => ['Country of origin', 'text'], 'manufacturer_barcode' => ['Manufacturer EAN / GTIN', 'text'], 'carton_dimensions' => ['Carton dimensions', 'text'],
        'storage' => ['Storage conditions', 'textarea'], 'shelf_life' => ['Shelf life', 'textarea'], 'preparation' => ['Preparation / thawing / baking', 'textarea'],
    ],
    'files' => [
        'image_link' => ['Image file or folder URL', 'url'], 'image_present' => ['Image declared present', 'checkbox'], 'image_verification' => ['Image identity verification', 'select', ['missing' => 'Missing', 'family' => 'Family reference', 'working' => 'Working value', 'source' => 'Source record', 'exact' => 'Product matched', 'conflict' => 'Conflicting sources', 'verified' => 'Verified']],
        'image_reopen' => ['Allow supplier image replacement for review', 'checkbox'],
        'ingredients_link' => ['Ingredients file or folder URL', 'url'], 'ingredients_present' => ['Ingredients declared present', 'checkbox'], 'ingredients_verification' => ['Ingredients identity verification', 'select', ['missing' => 'Missing', 'family' => 'Family reference', 'working' => 'Working value', 'source' => 'Source record', 'exact' => 'Product matched', 'conflict' => 'Conflicting sources', 'verified' => 'Verified']],
        'ingredients_reopen' => ['Allow supplier ingredients replacement for review', 'checkbox'], 'ingredients_text' => ['Complete ingredients — original text', 'textarea'], 'allergen_declaration' => ['Supplier allergen declaration', 'textarea'],
    ],
    'evidence' => ['supplier_notes' => ['Supplier comments', 'textarea'], 'owner_notes' => ['Private owner notes', 'textarea']],
    'source' => ['source_document' => ['Source document', 'text'], 'source_page' => ['Source page', 'integer'], 'source_text' => ['Original source text', 'textarea'], 'extracted_source_data' => ['Extracted source data — unverified', 'textarea']],
];
