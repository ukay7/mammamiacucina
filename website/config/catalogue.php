<?php
$products = array_merge((require __DIR__.'/most-loved.php')['products'], (require __DIR__.'/new-arrivals.php')['products']);
foreach ($products as &$product) { $product['category'] = 'cakes'; }
unset($product);
$products[] = ['name'=>'Operetta Pastries', 'slug'=>'operetta-pastries', 'image'=>'assets/images/mmc/category-pastries-hd.png', 'price_cents'=>null, 'category'=>'pastries'];
$products[] = ['name'=>'Cannoli & Cornetti', 'slug'=>'cannoli-cornetti', 'image'=>'assets/images/mmc/category-cannoli-hd.png', 'price_cents'=>null, 'category'=>'cannoli'];
return ['products'=>$products];
