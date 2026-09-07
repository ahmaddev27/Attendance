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
     * Authenticate an employee by employee number + password and issue
     * a personal access token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException when the credentials are invalid or the
     *                             account is inactive.
     */
    public function login(int $employeeNumber, string $password): array
    {
        $user = $this->users->findActiveByEmployeeNumber($employeeNumber);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'employee_number' => __('auth.failed'),
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
