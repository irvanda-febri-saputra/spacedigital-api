<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply CORS middleware to API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SingleSession::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'api.rate_limit' => \App\Http\Middleware\ApiRateLimiter::class,
            'api.key' => \App\Http\Middleware\ValidateApiKey::class,
            'turnstile' => \App\Http\Middleware\ValidateTurnstile::class,
            'single.session' => \App\Http\Middleware\SingleSession::class,
            'auth.api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
