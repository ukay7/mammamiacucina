<?php

use App\Http\Controllers\AboutPageController;
use App\Http\Controllers\Admin\AllergyController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\GatewaySettingController;
use App\Http\Controllers\Admin\GeneralSettingController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentReportController;
use App\Http\Controllers\Admin\PosController;
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
        Route::middleware(AdminAccess::class.':settings.manage')->prefix('content/{kind}')->where(['kind'=>'policies|departments|jobs'])->name('content.')->group(function(){
            Route::get('/',[\App\Http\Controllers\Admin\ContentController::class,'index'])->name('index');
            Route::put('/page-settings',[\App\Http\Controllers\Admin\ContentController::class,'page'])->name('page');
            Route::get('/create',[\App\Http\Controllers\Admin\ContentController::class,'create'])->name('create');
            Route::post('/',[\App\Http\Controllers\Admin\ContentController::class,'store'])->name('store');
            Route::get('/{id}/edit',[\App\Http\Controllers\Admin\ContentController::class,'edit'])->name('edit');
            Route::put('/{id}',[\App\Http\Controllers\Admin\ContentController::class,'update'])->name('update');
            Route::delete('/{id}',[\App\Http\Controllers\Admin\ContentController::class,'destroy'])->name('destroy');
        });

Route::middleware(AdminAccess::class.':catalogue.manage')->group(function(){
Route::get('/catalogue/{page}/preview',[\App\Http\Controllers\CatalogueBookController::class,'preview'])->name('catalogue.preview');
Route::resource('catalogue',\App\Http\Controllers\CatalogueBookController::class)->except('show')->parameters(['catalogue'=>'page']);
});
        Route::middleware(AdminAccess::class.':gallery.manage')->group(function () {
            Route::resource('gallery', GalleryController::class)->except('show')->parameters(['gallery' => 'event']);
            Route::delete('/gallery/{event}/photos/{photo}', [GalleryController::class, 'removePhoto'])->name('gallery.photos.remove');
        });
        Route::middleware(AdminAccess::class.':enquiries.manage')->group(function () {
            Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
            Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');
            Route::patch('/enquiries/{enquiry}', [EnquiryController::class, 'update'])->name('enquiries.update');
        });
        Route::middleware(AdminAccess::class.':pos.manage')->prefix('pos')->name('pos.')->group(function () {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::post('/customers', [PosController::class, 'customer'])->middleware('throttle:20,1')->name('customer');
            Route::get('/orders', [PosController::class, 'orders'])->name('orders');
            Route::post('/orders/{order}/handover', [PosController::class, 'handover'])->name('handover');
            Route::get('/orders/{order}', [PosController::class, 'order'])->name('order');
            Route::get('/products', [PosController::class, 'products'])->name('products');
            Route::get('/media/{product}/{media}', [ProductController::class, 'media'])->name('media');
            Route::post('/delivery-options', [\App\Http\Controllers\DeliveryQuoteController::class, 'options'])->name('delivery-options');
            Route::post('/quote', [PosController::class, 'quote'])->name('quote');
            Route::post('/sales', [PosController::class, 'store'])->block(10, 10)->name('store');
            Route::get('/receipts/{order}', [PosController::class, 'receipt'])->name('receipt');
        });
        Route::get('/product-labels', [PosController::class, 'labels'])->middleware(AdminAccess::class.':products.view')->name('products.labels');
        Route::resource('allergies', AllergyController::class)->except('show')->middleware(AdminAccess::class.':allergies.manage');
        Route::get('/reports/payments', [PaymentReportController::class, 'index'])->middleware(AdminAccess::class.':orders.view')->name('reports.payments');
        Route::get('/reports/payments/export', [PaymentReportController::class, 'export'])->middleware(AdminAccess::class.':orders.view')->name('reports.payments.export');
        foreach (['reconcile', 'cancel', 'refund'] as $action) {
            $route = Route::post('/payments/{payment}/'.$action, [PaymentReportController::class, $action])->middleware(AdminAccess::class.':orders.manage')->name('payments.'.$action);
            if ($action === 'refund') {
                $route->middleware(AdminAccess::class.':payments.refund');
            }
        }
        Route::post('/customers/{customer}/verify-email', [\App\Http\Controllers\Admin\CustomerController::class,'verifyEmail'])->middleware(AdminAccess::class.':users.manage')->name('customers.verify-email');
        Route::get('/customers/create', [\App\Http\Controllers\Admin\CustomerController::class,'create'])->middleware(AdminAccess::class.':users.manage')->name('customers.create');
        Route::post('/customers', [\App\Http\Controllers\Admin\CustomerController::class,'store'])->middleware(AdminAccess::class.':users.manage')->name('customers.store');
        Route::resource('quotations', \App\Http\Controllers\Admin\QuotationController::class)->except(['index','show'])->middleware(AdminAccess::class.':quotations.manage');
        Route::get('/quotations', [\App\Http\Controllers\Admin\QuotationController::class,'index'])->middleware(AdminAccess::class.':quotations.view|quotations.manage')->name('quotations.index');
        Route::get('/quotations/{quotation}/print', [\App\Http\Controllers\Admin\QuotationController::class,'print'])->middleware(AdminAccess::class.':quotations.view|quotations.manage')->name('quotations.print');
        Route::get('/quotations/{quotation}', [\App\Http\Controllers\Admin\QuotationController::class,'show'])->middleware(AdminAccess::class.':quotations.view|quotations.manage')->name('quotations.show');
        Route::get('/customers/{customer}/edit', [\App\Http\Controllers\Admin\CustomerController::class,'edit'])->middleware(AdminAccess::class.':users.manage')->name('customers.edit');
        Route::put('/customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class,'update'])->middleware(AdminAccess::class.':users.manage')->name('customers.update');
        Route::post('/customers/{customer}/password', [\App\Http\Controllers\Admin\CustomerController::class,'password'])->middleware(AdminAccess::class.':users.manage')->name('customers.password');
        Route::post('/customers/{customer}/approve-business', [\App\Http\Controllers\Admin\CustomerController::class,'approveBusiness'])->middleware(AdminAccess::class.':users.manage')->name('customers.approve-business');
        Route::post('/customers/{customer}/active', [\App\Http\Controllers\Admin\CustomerController::class,'active'])->middleware(AdminAccess::class.':users.manage')->name('customers.active');
        Route::post('/customers/{customer}/verification', [\App\Http\Controllers\Admin\CustomerController::class,'resend'])->middleware([AdminAccess::class.':users.manage','throttle:5,1'])->name('customers.resend');
        Route::get('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->middleware(AdminAccess::class.':orders.view')->name('customers.index');
        Route::get('/customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->middleware(AdminAccess::class.':orders.view')->name('customers.show');
        Route::get('/orders/completed', [OrderController::class, 'index'])->middleware(AdminAccess::class.':orders.view|warehouse.pack')->name('orders.completed');
        Route::get('/orders/export', [OrderController::class, 'export'])->middleware(AdminAccess::class.':orders.view|warehouse.pack')->name('orders.export');
        Route::get('/orders/{order}/print', [OrderController::class, 'printOrder'])->middleware(AdminAccess::class.':orders.view|warehouse.pack')->name('orders.print');
        Route::middleware(AdminAccess::class.':gateways.manage')->group(function () {
            Route::get('/settings/gateways', [GatewaySettingController::class, 'edit'])->name('gateways.edit');
            Route::put('/settings/gateways', [GatewaySettingController::class, 'update'])->name('gateways.update');
            Route::post('/settings/gateways/{provider}/{mode}/test', [GatewaySettingController::class, 'test'])->middleware('throttle:10,1')->name('gateways.test');
        });
        Route::get('/settings/contact', [\App\Http\Controllers\ContactPageController::class,'edit'])->middleware(AdminAccess::class.':contact.manage')->name('contact.edit');
        Route::put('/settings/contact', [\App\Http\Controllers\ContactPageController::class,'update'])->middleware(AdminAccess::class.':contact.manage')->name('contact.update');
        Route::get('/settings/about', [AboutPageController::class, 'edit'])->middleware(AdminAccess::class.':about.manage')->name('about.edit');
        Route::put('/settings/about', [AboutPageController::class, 'update'])->middleware(AdminAccess::class.':about.manage')->name('about.update');
        Route::middleware(AdminAccess::class.':settings.manage')->group(function () {
            Route::get('/delivery-rates', [\App\Http\Controllers\Admin\DeliveryRateController::class, 'index'])->name('delivery.index');
            Route::put('/delivery-services/{service}/description', [\App\Http\Controllers\Admin\DeliveryRateController::class, 'updateDescription'])->name('delivery.description');
            Route::put('/delivery-rates/{rate}', [\App\Http\Controllers\Admin\DeliveryRateController::class, 'update'])->whereNumber('rate')->name('delivery.update');
        });
        Route::post('/orders/delivery-options', [\App\Http\Controllers\DeliveryQuoteController::class, 'options'])->middleware(AdminAccess::class.':orders.manage')->name('orders.delivery-options');
        Route::middleware(AdminAccess::class.':settings.manage')->group(function () {
            Route::get('/settings/home-sections', [\App\Http\Controllers\Admin\HomeSectionController::class,'edit'])->name('home-sections.edit');
            Route::put('/settings/home-sections/{section}', [\App\Http\Controllers\Admin\HomeSectionController::class,'update'])->name('home-sections.update');
            Route::get('/settings/email/templates', [\App\Http\Controllers\Admin\EmailTemplateController::class,'index'])->name('email.templates.index');
            Route::get('/settings/email/templates/{key}', [\App\Http\Controllers\Admin\EmailTemplateController::class,'edit'])->name('email.templates.edit');
            Route::put('/settings/email/templates/{key}', [\App\Http\Controllers\Admin\EmailTemplateController::class,'update'])->name('email.templates.update');
            Route::get('/settings/email/history', [\App\Http\Controllers\Admin\EmailHistoryController::class, 'index'])->name('email.history');
            Route::get('/settings/email', [\App\Http\Controllers\Admin\SmtpSettingController::class, 'edit'])->name('email.edit');
            Route::put('/settings/email', [\App\Http\Controllers\Admin\SmtpSettingController::class, 'update'])->name('email.update');
            Route::post('/settings/email/test', [\App\Http\Controllers\Admin\SmtpSettingController::class, 'test'])->middleware('throttle:5,1')->name('email.test');
        });
        Route::get('/settings/theme', [\App\Http\Controllers\Admin\ThemeSettingController::class,'edit'])->middleware(AdminAccess::class.':settings.manage')->name('theme.edit');
        Route::put('/settings/theme', [\App\Http\Controllers\Admin\ThemeSettingController::class,'update'])->middleware(AdminAccess::class.':settings.manage')->name('theme.update');
        Route::get('/settings/general', [GeneralSettingController::class, 'edit'])->middleware(AdminAccess::class.':settings.manage')->name('settings.general');
        Route::put('/settings/general', [GeneralSettingController::class, 'update'])->middleware(AdminAccess::class.':settings.manage')->name('settings.update');
        Route::post('/orders/{order}/packing', [OrderController::class,'packing'])->middleware(AdminAccess::class.':warehouse.pack')->name('orders.packing');
        Route::post('/orders/{order}/amend', [OrderController::class,'amend'])->middleware(AdminAccess::class.':orders.manage')->name('orders.amend');
        Route::get('/orders/{order}/payments', [OrderController::class,'payments'])->middleware(AdminAccess::class.':orders.view|orders.manage')->name('orders.payments');
        Route::get('/orders/{order}/payments/{settlement}/receipt', [OrderController::class,'paymentReceipt'])->middleware(AdminAccess::class.':orders.view|orders.manage')->name('orders.payment-receipt');
        Route::post('/orders/{order}/settlement', [OrderController::class,'settlement'])->middleware(AdminAccess::class.':orders.manage')->name('orders.settlement');
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware(AdminAccess::class.':orders.manage')->name('orders.status');
        Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware(AdminAccess::class.':orders.manage')->name('orders.update');
        Route::get('/orders', [OrderController::class, 'index'])->middleware(AdminAccess::class.':orders.view|warehouse.pack')->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware(AdminAccess::class.':orders.view|warehouse.pack')->name('orders.show');
        Route::get('/banners', [BannerController::class, 'index'])->middleware(AdminAccess::class.':banners.view')->name('banners.index');
        Route::resource('banners', BannerController::class)->only(['create', 'store', 'edit', 'update'])->middleware(AdminAccess::class.':banners.manage');
        Route::get('/products/export', [ProductController::class, 'export'])->middleware(AdminAccess::class.':products.view')->name('products.export');
        Route::middleware(AdminAccess::class.':products.manage')->group(function () {
            foreach (['quote','save'] as $action) {
                Route::post('/products/{product}/pricing/'.$action, [\App\Http\Controllers\Admin\ProductPricingController::class,$action])->name('products.pricing.'.$action);
            }
        });

        foreach (['categories' => CategoryController::class, 'products' => ProductController::class] as $module => $controller) {
            Route::get('/'.$module, [$controller, 'index'])->middleware(AdminAccess::class.':'.$module.'.view')->name($module.'.index');
            Route::resource($module, $controller)->only(['create', 'store', 'edit', 'update'])->middleware(AdminAccess::class.':'.$module.'.manage');
        }
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware(AdminAccess::class.':categories.manage')->name('categories.destroy');
        Route::post('/products/bulk-category', [ProductController::class, 'bulkCategory'])->middleware(AdminAccess::class.':products.manage')->name('products.bulk-category');
        Route::get('/products/{product}/documents/{document}', [ProductController::class,'document'])->middleware(AdminAccess::class.':products.view')->name('products.document');
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
