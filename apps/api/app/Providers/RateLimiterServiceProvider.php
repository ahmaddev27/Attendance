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
        // Login: two independent rate limits — Laravel enforces the
        // tightest of them. Prior implementation keyed on
        // "identifier|ip" as a single bucket, which meant an attacker
        // with 10 IPs × 10 identifiers had 500 attempts/min bypassing
        // any per-key lockout. Splitting the bucket in two closes that
        // amplification:
        //   • 5/min per identifier — protects a single account from
        //     credential-stuffing regardless of source IP diversity.
        //   • 20/min per IP        — caps aggregate blast radius from a
        //     single origin walking many identifiers.
        // A legit user with a keyboard mistake still has 5 tries; a
        // shared-NAT office still gets 20 across everyone (well above
        // organic use).
        RateLimiter::for('login', function (Request $request) {
            $identifier = (string) ($request->input('identifier') ?? $request->input('employee_number') ?? '');
            $identifier = mb_strtolower(trim($identifier));

            $response = function () {
                return response()->json([
                    'message' => 'محاولات كثيرة. حاول مرة أخرى بعد دقيقة.',
                ], 429);
            };

            return [
                Limit::perMinute(5)->by($identifier)->response($response),
                Limit::perMinute(20)->by($request->ip())->response($response),
            ];
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

        // Self-service password reset — REQUEST step. The unauthenticated
        // caller submits an identifier and we may fire an SMS. Two costs
        // to bound:
        //   • Carrier bill / abuse: a single identifier being blasted with
        //     codes as an SMS-flood vector against a real employee.
        //   • Enumeration: probing many identifiers from one origin to see
        //     which ones trigger a delivery (indirectly leaked via carrier
        //     receipts, though not via our own response body).
        //
        // TWO independent limits — Laravel enforces the tightest of them.
        // The earlier composite "identifier|ip" bucket was bypassable by
        // an attacker rotating IPs while blasting the SAME identifier (SMS
        // flood against one employee). Splitting the bucket in two:
        //   • 5/hour per identifier — caps SMS flood against one number
        //     regardless of source IP diversity.
        //   • 20/hour per IP        — caps aggregate enumeration cost per
        //     origin walking many identifiers.
        RateLimiter::for('password-reset-request', function (Request $request) {
            $identifier = mb_strtolower(trim((string) $request->input('identifier')));

            return [
                Limit::perHour(5)->by('pwreset-id:'.$identifier),
                Limit::perHour(20)->by('pwreset-ip:'.$request->ip()),
            ];
        });

        // Self-service password reset — VERIFY step. Same split rationale:
        // per-identifier cap protects one account from OTP brute-force
        // regardless of source IP; per-IP cap bounds any single origin's
        // aggregate blast. With a 6-digit space (1e6) even at 5/min per
        // identifier it takes ~140 days to brute-force the 10-minute
        // window, which is safely infeasible.
        RateLimiter::for('password-reset-attempt', function (Request $request) {
            $identifier = mb_strtolower(trim((string) $request->input('identifier')));

            return [
                Limit::perMinute(5)->by('pwattempt-id:'.$identifier),
                Limit::perMinute(20)->by('pwattempt-ip:'.$request->ip()),
            ];
        });

        // Task creation: 60 per hour per user. Defense-in-depth against a
        // rogue employee (or a compromised session) mass-creating tasks
        // assigned to a target — the app has no per-user cap otherwise.
        // Keyed by user id when authenticated, IP as a fallback so an
        // unauthenticated hit (which would 401 anyway) still can't slip
        // past by leaving the limiter key null.
        // Attach to the route with `->middleware('throttle:create-tasks')`.
        RateLimiter::for('create-tasks', function (Request $request) {
            return Limit::perHour(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // Lead creation (Recruitment M11): 30 per hour per sales rep.
        // Caps how quickly a single compromised or misconfigured
        // integration (e.g. a broken LinkedIn scraper) can flood the
        // pipeline before an admin notices — the duplicate-warning
        // dedup path in LeadService blocks obvious clones but nothing
        // else bounds raw volume otherwise.
        // Attach with `->middleware('throttle:recruitment-lead-create')`.
        RateLimiter::for('recruitment-lead-create', function (Request $request) {
            return Limit::perHour(30)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
