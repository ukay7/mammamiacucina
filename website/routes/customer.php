<?php
use App\Http\Controllers\CustomerAccountController as Customer;
use App\Http\Middleware\CustomerAccess;
use Illuminate\Support\Facades\Route;
Route::get('/account/login',[Customer::class,'login'])->name('customer.login');
Route::post('/account/login',[Customer::class,'authenticate'])->middleware('throttle:5,1')->name('customer.login.store');
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
 Route::get('/admin/my-profile',[Customer::class,'profile'])->name('customer.profile');
 Route::put('/admin/my-profile',[Customer::class,'updateProfile'])->name('customer.profile.update');
 Route::get('/admin/my-orders',[Customer::class,'orders'])->name('customer.orders');
 Route::get('/admin/my-orders/{order}/print',[Customer::class,'printOrder'])->name('customer.order.print');
 Route::get('/admin/my-orders/{order}',[Customer::class,'order'])->name('customer.order');
});



Route::get('/account/forgot-password',[\App\Http\Controllers\CustomerPasswordController::class,'forgot'])->name('customer.password.forgot');
Route::post('/account/forgot-password',[\App\Http\Controllers\CustomerPasswordController::class,'send'])->middleware('throttle:5,1')->name('customer.password.send');
Route::get('/account/reset-password/{token}',[\App\Http\Controllers\CustomerPasswordController::class,'form'])->name('customer.password.reset');
Route::post('/account/reset-password',[\App\Http\Controllers\CustomerPasswordController::class,'update'])->middleware('throttle:5,1')->name('customer.password.update');
Route::get('/account/invitation/{user}',[\App\Http\Controllers\CustomerPasswordController::class,'invite'])->middleware(['signed','throttle:10,1'])->name('customer.invite');
