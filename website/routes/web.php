<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/admin.php';
Route::get('/banner-images/{banner}', [\App\Http\Controllers\Admin\BannerController::class,'image'])->name('banner.image');

Route::get('/products/{slug}', [\App\Http\Controllers\CatalogueController::class,'show'])->name('catalogue.product');
Route::get('/product-media/{product}/{media}', [\App\Http\Controllers\CatalogueController::class,'media'])->name('catalogue.media');
foreach (config('theme.pages') as $page) {
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
