<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/admin.php';
Route::get('/banner-images/{banner}', [\App\Http\Controllers\Admin\BannerController::class,'image'])->name('banner.image');

Route::get('/products/{slug}', [\App\Http\Controllers\CatalogueController::class,'show'])->name('catalogue.product');
Route::get('/product-media/{product}/{media}', [\App\Http\Controllers\CatalogueController::class,'media'])->name('catalogue.media');
Route::post('/cart/items/{product}', [\App\Http\Controllers\CartController::class,'save'])->block(10,10)->name('cart.add');
Route::patch('/cart/items/{product}', [\App\Http\Controllers\CartController::class,'save'])->block(10,10)->name('cart.update');
Route::delete('/cart/items/{product}', [\App\Http\Controllers\CartController::class,'remove'])->block(10,10)->name('cart.remove');
Route::post('/checkout',[\App\Http\Controllers\CheckoutController::class,'store'])->block(10,10)->middleware('throttle:20,1')->name('checkout.store');
foreach (config('theme.pages') as $page) {
    if(in_array($page,['checkout','order-success'])){Route::get('/'.$page,[\App\Http\Controllers\CheckoutController::class,$page==='checkout'?'create':'success'])->name('theme.'.$page);Route::redirect('/'.$page.'.html','/'.$page,301);continue;}
    if($page==='cart'){Route::get('/cart',[\App\Http\Controllers\CartController::class,'index'])->name('theme.cart');Route::redirect('/cart.html','/cart',301);continue;}
    if ($page === 'product-grid') {
        Route::get('/product-grid', [\App\Http\Controllers\CatalogueController::class,'index'])->name('theme.product-grid');
        Route::redirect('/product-grid.html','/product-grid',301);
        continue;
    }
    Route::view($page === 'index' ? '/' : '/'.$page, 'pages.'.$page)->name('theme.'.$page);
    Route::redirect('/'.$page.'.html', $page === 'index' ? '/' : '/'.$page, 301);
}
Route::redirect('/homepage.html', '/', 301);
Route::redirect('/search-result.html', '/product-grid', 301);
Route::redirect('/wishlist', '/whist-list', 301);
Route::fallback(fn () => response()->view('pages.404', [], 404));
