<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::middleware(['auth', 'auth.session', AdminAccess::class])->group(function () {
        Route::middleware(AdminAccess::class.':gallery.manage')->group(function(){
            Route::resource('gallery', \App\Http\Controllers\Admin\GalleryController::class)->except('show')->parameters(['gallery'=>'event']);
            Route::delete('/gallery/{event}/photos/{photo}',[\App\Http\Controllers\Admin\GalleryController::class,'removePhoto'])->name('gallery.photos.remove');
        });
        Route::middleware(AdminAccess::class.':enquiries.manage')->group(function(){
            Route::get('/enquiries',[\App\Http\Controllers\Admin\EnquiryController::class,'index'])->name('enquiries.index');
            Route::get('/enquiries/{enquiry}',[\App\Http\Controllers\Admin\EnquiryController::class,'show'])->name('enquiries.show');
            Route::patch('/enquiries/{enquiry}',[\App\Http\Controllers\Admin\EnquiryController::class,'update'])->name('enquiries.update');
        });
        Route::get('/orders/{order}/print',[\App\Http\Controllers\Admin\OrderController::class,'printOrder'])->middleware(AdminAccess::class.':orders.view')->name('orders.print');
        Route::get('/settings/general',[\App\Http\Controllers\Admin\GeneralSettingController::class,'edit'])->middleware(AdminAccess::class.':settings.manage')->name('settings.general');
        Route::put('/settings/general',[\App\Http\Controllers\Admin\GeneralSettingController::class,'update'])->middleware(AdminAccess::class.':settings.manage')->name('settings.update');
        Route::patch('/orders/{order}/status',[\App\Http\Controllers\Admin\OrderController::class,'updateStatus'])->middleware(AdminAccess::class.':orders.manage')->name('orders.status');
        Route::patch('/orders/{order}',[\App\Http\Controllers\Admin\OrderController::class,'update'])->middleware(AdminAccess::class.':orders.manage')->name('orders.update');
        Route::get('/orders',[\App\Http\Controllers\Admin\OrderController::class,'index'])->middleware(AdminAccess::class.':orders.view')->name('orders.index');
        Route::get('/orders/{order}',[\App\Http\Controllers\Admin\OrderController::class,'show'])->middleware(AdminAccess::class.':orders.view')->name('orders.show');
        Route::get('/banners', [\App\Http\Controllers\Admin\BannerController::class, 'index'])->middleware(AdminAccess::class.':banners.view')->name('banners.index');
        Route::resource('banners', \App\Http\Controllers\Admin\BannerController::class)->only(['create','store','edit','update'])->middleware(AdminAccess::class.':banners.manage');
        foreach (['categories' => CategoryController::class, 'products' => ProductController::class] as $module => $controller) {
            Route::get('/'.$module, [$controller, 'index'])->middleware(AdminAccess::class.':'.$module.'.view')->name($module.'.index');
            Route::resource($module, $controller)->only(['create', 'store', 'edit', 'update'])->middleware(AdminAccess::class.':'.$module.'.manage');
        }
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware(AdminAccess::class.':categories.manage')->name('categories.destroy');
        Route::post('/products/bulk-category', [ProductController::class, 'bulkCategory'])->middleware(AdminAccess::class.':products.manage')->name('products.bulk-category');
        Route::get('/products/{product}/media/{media}', [ProductController::class, 'media'])->middleware(AdminAccess::class.':products.view')->name('products.media');
        Route::get('/products/{product}', [ProductController::class, 'show'])->middleware(AdminAccess::class.':products.view')->name('products.show');
        Route::get('/imports/template', [ProductImportController::class, 'template'])->middleware(AdminAccess::class.':imports.manage')->name('imports.template');
        Route::get('/imports', [ProductImportController::class, 'index'])->middleware(AdminAccess::class.':imports.view')->name('imports.index');
        Route::post('/imports', [ProductImportController::class, 'store'])->middleware(AdminAccess::class.':imports.manage')->name('imports.store');
        Route::get('/imports/{import}', [ProductImportController::class, 'show'])->middleware(AdminAccess::class.':imports.view')->name('imports.show');
        Route::get('/imports/{import}/download', [ProductImportController::class, 'download'])->middleware(AdminAccess::class.':imports.view')->name('imports.download');
        Route::post('/imports/{import}/commit', [ProductImportController::class, 'commit'])->middleware(AdminAccess::class.':imports.manage')->name('imports.commit');
        Route::get('/inventory', [InventoryController::class, 'index'])->middleware(AdminAccess::class.':inventory.view')->name('inventory.index');
        Route::get('/inventory/{product}', [InventoryController::class, 'show'])->middleware(AdminAccess::class.':inventory.view')->name('inventory.show');
        Route::post('/inventory/{product}', [InventoryController::class, 'adjust'])->middleware(AdminAccess::class.':inventory.manage')->name('inventory.adjust');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');
        Route::middleware(AdminAccess::class.':users.view')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
        });
        Route::middleware(AdminAccess::class.':users.manage')->group(function () {
            Route::resource('users', UserController::class)->except(['index', 'show', 'destroy']);
        });
        Route::get('/user-types', [RoleController::class, 'index'])->middleware(AdminAccess::class.':roles.view')->name('roles.index');
        Route::middleware(AdminAccess::class.':roles.manage')->group(function () {
            Route::get('/user-types/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('/user-types', [RoleController::class, 'store'])->name('roles.store');
            Route::get('/user-types/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('/user-types/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('/user-types/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });
    });
});
