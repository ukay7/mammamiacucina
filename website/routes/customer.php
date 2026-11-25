<?php
use App\Http\Controllers\CustomerAccountController as Customer;
use App\Http\Controllers\CustomerCatalogueController;
use App\Http\Controllers\CreditApplicationController;
use App\Http\Controllers\CustomerOverviewController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Middleware\CustomerAccess;
use Illuminate\Support\Facades\Route;
Route::get('/account/login',[Customer::class,'login'])->name('customer.login');
Route::post('/account/login',[Customer::class,'authenticate'])->middleware('throttle:5,1')->name('customer.login.store');
Route::get('/account/credit-application/{application}/open',[CreditApplicationController::class,'open'])->whereNumber('application')->name('customer.credit-applications.open');
Route::get('/account/register',[Customer::class,'register'])->name('customer.register');
Route::post('/account/register',[Customer::class,'store'])->middleware('throttle:5,1')->name('customer.register.store');
Route::middleware(CustomerAccess::class.':no')->group(function(){
 Route::get('/account/business-approval',function(){return auth()->user()->businessApprovalPending() ? response()->view('customer.business-pending')->header('Cache-Control','no-store, private') : redirect()->route('customer.orders');})->name('customer.business.pending');
 Route::get('/account/verify-email',[Customer::class,'notice'])->name('customer.verify.notice');
 Route::get('/account/verify/{id}/{hash}',[Customer::class,'verify'])->middleware(['signed','throttle:10,1'])->name('customer.verify');
 Route::post('/account/resend-verification',[Customer::class,'resend'])->middleware('throttle:1,1')->name('customer.verify.resend');
 Route::post('/account/logout',[Customer::class,'logout'])->name('customer.logout');
});
Route::middleware(CustomerAccess::class)->group(function(){
 Route::get('/admin/my-account',[CustomerOverviewController::class,'dashboard'])->name('customer.dashboard');
 Route::get('/admin/my-credit',[CustomerOverviewController::class,'credit'])->name('customer.credit');
 Route::prefix('/admin/my-credit/applications')->name('customer.credit-applications.')->group(function () {
     Route::get('/', [CreditApplicationController::class, 'index'])->name('index');
     Route::post('/', [CreditApplicationController::class, 'start'])->middleware('throttle:8,1,credit-application-start')->name('start');
     Route::get('/{application}', [CreditApplicationController::class, 'show'])->whereNumber('application')->name('show');
     Route::post('/{application}/prepare', [CreditApplicationController::class, 'prepare'])->whereNumber('application')->middleware('throttle:20,1,credit-application-prepare')->name('prepare');
     Route::post('/{application}/submit', [CreditApplicationController::class, 'submit'])->whereNumber('application')->middleware('throttle:6,1,credit-application-signature')->name('submit');
     Route::get('/{application}/draft', [CreditApplicationController::class, 'draft'])->whereNumber('application')->middleware('throttle:15,1,credit-application-draft')->name('draft');
 });
 Route::get('/admin/my-receipts',[CustomerOverviewController::class,'receipts'])->name('customer.receipts');
 Route::get('/admin/my-statement',[\App\Http\Controllers\CustomerDocumentController::class,'statement'])->middleware('throttle:12,1')->name('customer.statement');
 Route::get('/admin/my-invoices',[InvoicePaymentController::class,'invoices'])->name('customer.invoices');
 Route::post('/admin/my-invoices/pay',[InvoicePaymentController::class,'start'])->middleware('throttle:10,1')->block(30,30)->name('customer.invoice-payments.start');
 Route::get('/admin/my-invoices/payments/{invoicePayment:reference}',[InvoicePaymentController::class,'show'])->name('customer.invoice-payments.show');
 Route::post('/admin/my-invoices/payments/{invoicePayment:reference}/confirm',[InvoicePaymentController::class,'confirm'])->middleware('throttle:20,1')->name('customer.invoice-payments.confirm');
 Route::post('/admin/my-invoices/payments/{invoicePayment:reference}/check',[InvoicePaymentController::class,'check'])->middleware('throttle:12,1')->name('customer.invoice-payments.check');
 Route::get('/admin/my-support',[SupportTicketController::class,'index'])->name('customer.support.index');
 Route::post('/admin/my-support',[SupportTicketController::class,'store'])->middleware('throttle:8,1')->name('customer.support.store');
 Route::get('/admin/my-support/{ticket}',[SupportTicketController::class,'show'])->whereNumber('ticket')->name('customer.support.show');
 Route::post('/admin/my-support/{ticket}/reply',[SupportTicketController::class,'reply'])->whereNumber('ticket')->middleware('throttle:20,1')->name('customer.support.reply');
 Route::get('/admin/my-catalogue',[CustomerCatalogueController::class,'index'])->block(10,10)->name('customer.catalogue');
 Route::post('/admin/my-catalogue/cart',[CustomerCatalogueController::class,'save'])->block(10,10)->name('customer.catalogue.cart');
 Route::post('/admin/my-catalogue/cart/{product}/remove',[CustomerCatalogueController::class,'remove'])->whereNumber('product')->block(10,10)->name('customer.catalogue.cart.remove');
 Route::get('/admin/my-documents',[\App\Http\Controllers\CustomerDocumentController::class,'index'])->name('customer.documents');
 Route::get('/admin/my-documents/{kind}/{document}',[\App\Http\Controllers\CustomerDocumentController::class,'customerFile'])->whereNumber('document')->name('customer.documents.file');
 Route::get('/admin/my-profile',[Customer::class,'profile'])->name('customer.profile');
 Route::put('/admin/my-profile',[Customer::class,'updateProfile'])->name('customer.profile.update');
 Route::get('/admin/my-orders',[Customer::class,'orders'])->name('customer.orders');
 Route::get('/admin/my-orders/{order}/print',[Customer::class,'printOrder'])->name('customer.order.print');
 Route::post('/admin/my-orders/{order}/reorder',[CustomerCatalogueController::class,'reorder'])->middleware('throttle:15,1')->block(10,10)->name('customer.order.reorder');
 Route::get('/admin/my-orders/{order}',[Customer::class,'order'])->name('customer.order');
});



Route::get('/account/forgot-password',[\App\Http\Controllers\CustomerPasswordController::class,'forgot'])->name('customer.password.forgot');
Route::post('/account/forgot-password',[\App\Http\Controllers\CustomerPasswordController::class,'send'])->middleware('throttle:5,1')->name('customer.password.send');
Route::get('/account/reset-password/{token}',[\App\Http\Controllers\CustomerPasswordController::class,'form'])->name('customer.password.reset');
Route::post('/account/reset-password',[\App\Http\Controllers\CustomerPasswordController::class,'update'])->middleware('throttle:5,1')->name('customer.password.update');
Route::get('/account/invitation/{user}',[\App\Http\Controllers\CustomerPasswordController::class,'invite'])->middleware(['signed','throttle:10,1'])->name('customer.invite');
