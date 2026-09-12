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
        // DUAL AUTH MODE.
        //
        // Web SPA → Sanctum SPA stateful mode. A request whose Origin
        // matches SANCTUM_STATEFUL_DOMAINS gets the session + CSRF
        // middleware appended, so the browser stores the session in an
        // httpOnly cookie the JS cannot read. Login on this path returns
        // ONLY the user (no bearer token in the response body), which
        // closes the "XSS steals the bearer from localStorage" class of
        // attack.
        //
        // Mobile → Bearer tokens. The RN app has no cookie jar shared
        // with a browser, so EnsureFrontendRequestsAreStateful is a
        // no-op for it — the request falls through to the standard
        // PersonalAccessToken guard. Nothing changes for mobile.
        //
        // Both flows resolve through `auth:sanctum`, which happily
        // accepts either a session cookie OR an Authorization bearer,
        // so downstream controllers don't need branching.
        $middleware->statefulApi();

        // spatie/laravel-permission ships three middleware but doesn't
        // auto-register them in Laravel 11's slim bootstrap — we do it here
        // so `->middleware('permission:manage-users')` works on any route.
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Trust every hop's X-Forwarded-* headers so `$request->ip()` /
        // `->isSecure()` / `->getHost()` return the ORIGINAL client
        // values, not the reverse-proxy peer. Without this, every
        // rate-limiter key falls onto the proxy's own IP (bypassing
        // per-client caps) and every "session cookie only over https"
        // check trips on the plain-http proxy → app hop.
        //
        // `at: '*'` trusts any proxy — safe behind a single-hop reverse
        // proxy (Apache/nginx/Cloudflare terminating TLS in front of this
        // container). Ops can tighten to the actual proxy CIDR once the
        // topology stabilises.
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
