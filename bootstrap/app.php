<?php

use App\Http\Middleware\EnsureSessionAdmin;
use App\Http\Middleware\EnsureSessionAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'session.auth' => EnsureSessionAuthenticated::class,
            'session.admin' => EnsureSessionAdmin::class,
        ]);

        // PayU POSTs browser callbacks without a Laravel CSRF token; wildcard covers all routes under /payu/.
        $middleware->validateCsrfTokens(except: [
            'payu/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
