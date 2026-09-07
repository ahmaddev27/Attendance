<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Models\User;

class UserRepository
{
    /**
     * Find an active user by their employee number.
     *
     * Inactive/disabled accounts are excluded at the query level so a
     * deactivated employee can never authenticate, regardless of how
     * this lookup is used elsewhere.
     */
    public function findActiveByEmployeeNumber(int $employeeNumber): ?User
    {
        return User::query()
            ->where('employee_number', $employeeNumber)
            ->where('is_active', true)
            ->first();
    }
}
