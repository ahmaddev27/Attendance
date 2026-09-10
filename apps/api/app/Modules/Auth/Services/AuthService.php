<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * A pre-computed 12-round bcrypt hash used to burn wall-clock time on
     * the "unknown identifier" branch of login(). Without this, an
     * unknown identifier short-circuits before Hash::check() runs, and
     * the ~150ms cost of a real bcrypt check leaks whether the account
     * exists — an attacker can enumerate valid employee_numbers by
     * timing alone. Doing a throwaway Hash::check() against this fixed
     * hash equalises the two branches.
     *
     * The plaintext behind this hash is intentionally never reachable
     * from configured user rows (arbitrary long random string) and its
     * cost matches the framework default so timing parity holds even
     * as the real user's hash was written by bcrypt-12.
     */
    private const DUMMY_BCRYPT_HASH = '$2y$12$vHRsN4N94.ksJQcwqlVTp..4SoCKuit6XxxqVtvHJIg/yxWpRaviy';

    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * Authenticate a user by either their employee number OR their email
     * address, plus password. Returns a fresh personal access token.
     *
     * The single-field UX matters: employees only remember their number,
     * managers and admins usually type their email. Auto-detects which
     * one the caller passed — an '@' anywhere in $identifier switches the
     * lookup to the email path.
     *
     * $tokenName is the Sanctum token name (bucket) for this login. Prior
     * tokens with the SAME name are revoked before the new one is issued
     * so a relogin doesn't leave a 60-day pile of live tokens behind. Names
     * are per-client (e.g. `web`, `mobile`) so a mobile relogin only
     * rotates mobile tokens and never kicks an active web session.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException when the credentials are invalid or the
     *                             account is inactive.
     */
    public function login(int|string $identifier, string $password, string $tokenName = 'web'): array
    {
        $identifier = trim((string) $identifier);

        $user = str_contains($identifier, '@')
            ? $this->users->findActiveByEmail($identifier)
            : $this->users->findActiveByEmployeeNumber((int) $identifier);

        if (! $user) {
            // Burn the same wall-clock a real Hash::check would cost so
            // "unknown identifier" and "wrong password" are timing-
            // indistinguishable. Result is discarded — the throw is the
            // real outcome. See DUMMY_BCRYPT_HASH for rationale.
            Hash::check($password, self::DUMMY_BCRYPT_HASH);

            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        // Revoke any prior tokens issued under the same client bucket
        // BEFORE minting the new one. Sanctum otherwise keeps them alive
        // for their full expiration window (default 60 days) and the
        // personal_access_tokens table grows unboundedly for any user
        // who logs in more than once.
        $user->tokens()->where('name', $tokenName)->delete();

        $token = $user->createToken($tokenName)->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        return ['user' => $user, 'token' => $token];
    }

    /**
     * SPA login: verify credentials and open a Laravel SESSION for the
     * user — no bearer token issued, nothing exposed to the JS layer.
     * The session id lives in an httpOnly cookie the browser attaches
     * automatically; XSS cannot read it. Used by the web app via the
     * stateful (`EnsureFrontendRequestsAreStateful`) middleware chain.
     *
     * @throws ValidationException on bad credentials or inactive user.
     */
    public function loginStateful(int|string $identifier, string $password): User
    {
        $identifier = trim((string) $identifier);

        $user = str_contains($identifier, '@')
            ? $this->users->findActiveByEmail($identifier)
            : $this->users->findActiveByEmployeeNumber((int) $identifier);

        if (! $user) {
            Hash::check($password, self::DUMMY_BCRYPT_HASH);

            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        // Bind the user to Laravel's session guard. The controller will
        // then regenerate the session id (session-fixation defense).
        Auth::guard('web')->login($user);

        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }

    /**
     * Revoke the access token that authenticated the current request.
     * SPA sessions are torn down separately by the controller (session
     * invalidate + regenerateToken).
     */
    public function logout(User $user): void
    {
        // currentAccessToken() returns a TransientToken for session
        // logins — nothing to delete there, the controller handles
        // that path. This delete only fires on real bearer tokens.
        $token = $user->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }
    }
}
