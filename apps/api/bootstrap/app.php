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
    ->withMiddleware(function (Middleware $middleware) {
        // Pure Bearer-token auth via Sanctum PersonalAccessTokens.
        // We intentionally do NOT enable EnsureFrontendRequestsAreStateful:
        // the SPA lives on the same origin as the API, so Sanctum would
        // upgrade every request to a session-based (CSRF-required) request.
        // Bearer tokens in Authorization headers work correctly without it.

        // spatie/laravel-permission ships three middleware but doesn't
        // auto-register them in Laravel 11's slim bootstrap — we do it here
        // so `->middleware('permission:manage-users')` works on any route.
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
