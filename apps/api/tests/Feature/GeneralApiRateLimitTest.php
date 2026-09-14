<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;

/**
 * docs/v2 NFR: a general API rate limit on top of the per-route ones.
 * Signed-in callers get a bucket per user, sized so a page that fires a
 * handful of requests still leaves room for fast navigation. Guests only
 * get a flood cap per IP, because a whole office reaches us through one
 * public IP, and the public scan routes keep only their own limits.
 *
 * @return list<string>
 */
function resolvedRouteMiddleware(string $method, string $uri): array
{
    $route = app('router')->getRoutes()->match(Request::create($uri, $method));

    return app(Router::class)->gatherRouteMiddleware($route);
}

test('a signed-in caller is limited to 120 API requests a minute', function () {
    $token = User::factory()->create()->createToken('mobile')->plainTextToken;

    for ($request = 0; $request < 120; $request++) {
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
    }

    $this->withToken($token)->getJson('/api/auth/me')->assertTooManyRequests();
});

test('each signed-in caller spends only their own budget', function () {
    $busy = User::factory()->create()->createToken('web')->plainTextToken;
    $quiet = User::factory()->create()->createToken('web')->plainTextToken;

    for ($request = 0; $request < 121; $request++) {
        $this->withToken($busy)->getJson('/api/auth/me');
    }

    // The sanctum guard caches the resolved user for the life of the test
    // application; production resolves it per request.
    app('auth')->forgetGuards();

    $this->withToken($quiet)->getJson('/api/auth/me')->assertOk();
});

test('guests get only a per-IP flood cap that a whole office stays under', function () {
    // Laravel runs auth before any throttle, so a guest on a protected route
    // is answered 401 first, and every public route has a tighter limit of
    // its own. The cap is a backstop, so its definition is checked directly.
    $guest = Request::create('/api/health', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);
    $limit = RateLimiter::limiter('api')($guest);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(600)
        ->and($limit->key)->toBe('api-ip:203.0.113.9');
});

test('the public scan routes keep only their own per-route limits', function () {
    $general = ThrottleRequests::class.':api';

    expect(resolvedRouteMiddleware('POST', '/api/scan/check-in'))->not->toContain($general)
        ->and(resolvedRouteMiddleware('POST', '/api/scan/status'))->not->toContain($general)
        ->and(resolvedRouteMiddleware('GET', '/api/scan/device/'.str_repeat('a', 64)))->not->toContain($general)
        ->and(resolvedRouteMiddleware('GET', '/api/auth/me'))->toContain($general);
});
