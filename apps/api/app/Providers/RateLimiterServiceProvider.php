<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Named rate-limiter definitions. Routes reference these via
 * `->middleware('throttle:<name>')`. Kept in one place so the security
 * posture is auditable from a single file rather than scattered across
 * routes/api.php.
 *
 * Registered from config/app.php `providers` array.
 */
class RateLimiterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Login: 5 attempts per minute per (identifier + IP). An attacker
        // enumerating employee_numbers gets rate-limited before they
        // can walk a meaningful chunk of the id space, and a legit user
        // with a keyboard mistake has plenty of headroom.
        //
        // Keying by identifier means one attacker across many IPs still
        // gets throttled per-account; adding IP means two people behind
        // the same NAT don't lock each other out.
        RateLimiter::for('login', function (Request $request) {
            $identifier = (string) ($request->input('identifier') ?? $request->input('employee_number') ?? '');
            $identifier = mb_strtolower(trim($identifier));

            return Limit::perMinute(5)
                ->by($identifier.'|'.$request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'محاولات كثيرة. حاول مرة أخرى بعد دقيقة.',
                    ], 429);
                });
        });

        // Push-token registration: 20 per hour per user. Prevents a
        // compromised app from ballooning the push_tokens table.
        RateLimiter::for('push-tokens', function (Request $request) {
            return Limit::perHour(20)->by(optional($request->user())->id ?: $request->ip());
        });

        // Reset password (admin action): 30 per hour per admin.
        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perHour(30)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
