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
     * @return array{user: User, token: string}
     *
     * @throws ValidationException when the credentials are invalid or the
     *                             account is inactive.
     */
    public function login(int|string $identifier, string $password): array
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

        $token = $user->createToken('web')->plainTextToken;

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
