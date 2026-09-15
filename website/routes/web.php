<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/admin.php';
Route::get('/allergy-icons/{allergy}',[\App\Http\Controllers\Admin\AllergyController::class,'icon'])->name('allergy.icon');
Route::post('/contact',[\App\Http\Controllers\ContactController::class,'store'])->middleware('throttle:5,1,contact-form')->name('contact.store');
Route::get('/gallery/events/{event}',[\App\Http\Controllers\GalleryController::class,'show'])->name('gallery.event');
Route::get('/gallery/photos/{photo}',[\App\Http\Controllers\GalleryController::class,'image'])->name('gallery.image');
Route::get('/site-logo', [\App\Http\Controllers\Admin\GeneralSettingController::class,'logo'])->name('site.logo');
Route::get('/track-order',[\App\Http\Controllers\OrderTrackingController::class,'form'])->name('order.track-form');
Route::post('/track-order',[\App\Http\Controllers\OrderTrackingController::class,'lookup'])->middleware('throttle:6,1,order-lookup')->name('order.lookup');
Route::post('/track-order/{token}/verify',[\App\Http\Controllers\OrderTrackingController::class,'verify'])->middleware('throttle:6,1,order-email-verify')->name('order.verify');
Route::get('/track-order/{token}/print',[\App\Http\Controllers\OrderTrackingController::class,'printOrder'])->middleware('throttle:60,1,order-tracking')->name('order.track-print');
Route::get('/track-order/{token}',[\App\Http\Controllers\OrderTrackingController::class,'show'])->middleware('throttle:60,1,order-tracking')->name('order.track');
Route::get('/order-print',[\App\Http\Controllers\CheckoutController::class,'printOrder'])->name('order.print');
Route::get('/category-images/{category}', [\App\Http\Controllers\Admin\CategoryController::class,'image'])->name('category.image');
Route::get('/banner-images/{banner}', [\App\Http\Controllers\Admin\BannerController::class,'image'])->name('banner.image');

Route::get('/products/{slug}', [\App\Http\Controllers\CatalogueController::class,'show'])->name('catalogue.product');
Route::get('/product-media/{product}/{media}', [\App\Http\Controllers\CatalogueController::class,'media'])->name('catalogue.media');
Route::post('/cart/items/{product}', [\App\Http\Controllers\CartController::class,'save'])->block(10,10)->name('cart.add');
Route::patch('/cart/items/{product}', [\App\Http\Controllers\CartController::class,'save'])->block(10,10)->name('cart.update');
Route::delete('/cart/items/{product}', [\App\Http\Controllers\CartController::class,'remove'])->block(10,10)->name('cart.remove');
Route::post('/checkout',[\App\Http\Controllers\CheckoutController::class,'store'])->block(10,10)->middleware('throttle:20,1')->name('checkout.store');
foreach (config('theme.pages') as $page) {
    if($page==='gallery'){Route::get('/gallery',[\App\Http\Controllers\GalleryController::class,'index'])->name('theme.gallery');Route::redirect('/gallery.html','/gallery',301);continue;}
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
