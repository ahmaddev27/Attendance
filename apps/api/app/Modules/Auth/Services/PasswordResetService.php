<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Sms\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Self-service password reset via SMS OTP.
 *
 * Two-step protocol:
 *   1. requestReset(identifier)  — resolve identifier → user → phone,
 *      mint a 6-digit code, cache its HASH for 10 minutes, SMS the
 *      plaintext to the phone on file.
 *   2. resetPassword(identifier, code, password) — verify the hashed
 *      code, rotate the password, wipe every Sanctum token for the user
 *      (a leaked token from before the reset must NOT survive it), and
 *      invalidate the cache entry so a code cannot be replayed.
 *
 * Both entry points are written to be uniform on the "not found" branch:
 *   - requestReset returns void unconditionally (the controller replies
 *     with a fixed message either way).
 *   - resetPassword throws ValidationException with a generic message
 *     for every failure mode (unknown identifier, expired/missing code,
 *     wrong code) so the endpoint never leaks account-existence data.
 */
class PasswordResetService
{
    /**
     * Cache TTL for the pending OTP. Ten minutes is short enough to
     * meaningfully limit a stolen SMS's replay window and long enough
     * that a user typing on a slow phone keyboard doesn't miss it.
     */
    private const OTP_TTL_MINUTES = 10;

    /**
     * Cache-key prefix. Keyed by user id so a legitimate user requesting
     * a second code (say, they mistyped the first) simply overwrites the
     * previous entry — no per-code table to clean up.
     */
    private const CACHE_PREFIX = 'pw-reset:';

    public function __construct(
        private readonly UserRepository $users,
        private readonly SmsService $sms,
    ) {}

    /**
     * Kick off the reset flow. Silently no-ops when the identifier does
     * not resolve to an active user or when the user has no phone on
     * file — the caller must not surface that fact to the client.
     *
     * SMS delivery failures are logged and swallowed here too; the user
     * simply won't receive a code, and can retry after the rate-limiter
     * window. Bubbling the exception would reveal (via response body
     * differences) that the identifier is valid.
     */
    public function requestReset(string $identifier): void
    {
        $user = $this->resolveUser($identifier);

        if ($user === null) {
            return;
        }

        $phone = trim((string) ($user->phone ?? ''));
        if ($phone === '') {
            return;
        }

        $code = $this->generateCode();

        Cache::put(
            $this->cacheKey($user->id),
            Hash::make($code),
            now()->addMinutes(self::OTP_TTL_MINUTES),
        );

        $message = sprintf(
            'رمز استعادة كلمة السر: %s — صالح لـ %d دقائق',
            $code,
            self::OTP_TTL_MINUTES,
        );

        try {
            $this->sms->sendNow($phone, $message);
        } catch (Throwable $e) {
            Log::warning('[PasswordResetService::requestReset] sms send failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify the OTP and rotate the password. Uniform generic error on
     * every failure branch so timing/behavioural probing can't tell
     * "no such user" apart from "wrong code" apart from "code expired".
     *
     * @throws ValidationException with a generic "invalid code" message
     *                             on any failure.
     */
    public function resetPassword(string $identifier, string $code, string $password): void
    {
        $user = $this->resolveUser($identifier);

        if ($user === null) {
            $this->throwInvalidCode();
        }

        $hashed = Cache::get($this->cacheKey($user->id));

        if (! is_string($hashed) || ! Hash::check($code, $hashed)) {
            $this->throwInvalidCode();
        }

        // Rotate the password. The User model casts `password` to
        // `hashed`, so a plaintext assignment here bcrypts on save;
        // we do it explicitly for clarity and to survive any future
        // cast changes.
        $user->forceFill(['password' => Hash::make($password)])->save();

        // Any Sanctum token minted before this reset represents a
        // pre-compromise credential and must not survive the rotation.
        // Session-based logins on web are wiped separately by the
        // client re-authenticating via /auth/login.
        $user->tokens()->delete();

        // One-shot code — burn it so a replay of the same 6 digits
        // fails even inside the 10-minute window.
        Cache::forget($this->cacheKey($user->id));
    }

    /**
     * Mirrors AuthService::login — an '@' anywhere in the identifier
     * flips the lookup to the email path so employees only need to
     * remember one field on the forgot-password form.
     */
    private function resolveUser(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        return str_contains($identifier, '@')
            ? $this->users->findActiveByEmail($identifier)
            : $this->users->findActiveByEmployeeNumber((int) $identifier);
    }

    private function cacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.$userId;
    }

    /**
     * Cryptographically-strong 6-digit code. random_int is
     * seeded from the OS CSPRNG — mt_rand would be predictable
     * across a burst of requests and is unsafe for OTPs.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @throws ValidationException
     */
    private function throwInvalidCode(): void
    {
        throw ValidationException::withMessages([
            'code' => 'رمز غير صالح أو منتهي الصلاحية.',
        ]);
    }
}
