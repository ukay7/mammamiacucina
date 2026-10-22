<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Windows Apache shares its process environment across request threads. Keep
// uncached dotenv values request-local when many product images load together.
if (PHP_OS_FAMILY === 'Windows' && PHP_SAPI === 'apache2handler') {
    \Illuminate\Support\Env::disablePutenv();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\ArchivedOrderReadOnly::class]);
        $middleware->validateCsrfTokens(except: ['payments/webhooks/stripe', 'payments/webhooks/paypal', 'payments/card-events']);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['credentials','api_key']); //
    })->create();
