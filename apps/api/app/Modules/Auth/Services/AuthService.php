<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
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

        if (! $user || ! Hash::check($password, $user->password)) {
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
     * Revoke the access token that authenticated the current request.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
