<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationTrackingController;

// No session/CSRF middleware: webhook signatures and unguessable delivery tokens
// authenticate these endpoints. GET unsubscribe only displays a confirmation.
Route::prefix('notifications')->name('notifications.')->group(function() {
    Route::get('/open/{token}.gif',[NotificationTrackingController::class,'open'])->where('token','[A-Za-z0-9]{64}')->middleware('throttle:600,1')->name('open');
    Route::get('/click/{token}/{link}',[NotificationTrackingController::class,'click'])->where(['token'=>'[A-Za-z0-9]{64}','link'=>'[A-Za-z0-9]{40}'])->middleware('throttle:600,1')->name('click');
    Route::match(['GET','POST'],'/unsubscribe/{token}',[NotificationTrackingController::class,'unsubscribe'])->where('token','[A-Za-z0-9]{64}')->middleware('throttle:60,1')->name('unsubscribe');
    Route::post('/webhooks/resend',[NotificationTrackingController::class,'webhook'])->middleware('throttle:600,1')->name('webhook');
});
