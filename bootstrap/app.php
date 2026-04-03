<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureSessionAdmin;
use App\Http\Middleware\EnsureSessionAuthenticated;

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

        $middleware->validateCsrfTokens(except: [
            'payu/auction/success',
            'payu/auction/failure',
            'payu/registration/success',
            'payu/registration/failure',
            'payu/wallet/success',
            'payu/wallet/failure',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
