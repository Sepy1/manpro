<?php

use App\Http\Middleware\EnsureAdminTwoFactorVerified;
use App\Http\Middleware\LogUserMenuActivity;
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
            'admin.2fa' => EnsureAdminTwoFactorVerified::class,
            'menu.activity' => LogUserMenuActivity::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhook/whatsapp',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
