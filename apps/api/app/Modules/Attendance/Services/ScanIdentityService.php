<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\Employee;
use App\Models\User;
use App\Modules\Attendance\Exceptions\AttendanceModuleException;
use App\Modules\Attendance\Exceptions\InvalidScanCredentialsException;
use App\Modules\Attendance\Exceptions\ScanIdentityException;
use App\Modules\Attendance\Exceptions\ScanPinException;
use App\Modules\Employees\Repositories\EmployeeRepository;
use App\Shared\Enums\EmployeeStatus;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Decides which employee a scan is recorded for. A bearer token (the
 * mobile app) already proves identity; the public kiosk page only has a
 * typed employee number, which is not secret, so it must be paired with
 * the employee's scan PIN once enforcement is switched on.
 */
class ScanIdentityService
{
    private const UNKNOWN_EMPLOYEE_MESSAGE = 'Unknown employee number.';

    public function __construct(
        private readonly ScanPinService $scanPins,
        private readonly EmployeeRepository $employees,
    ) {}

    /**
     * @throws AttendanceModuleException
     */
    public function resolve(?string $bearerToken, ?int $employeeNumber, ?string $pin): Employee
    {
        return $bearerToken !== null
            ? $this->resolveFromBearerToken($bearerToken, $employeeNumber)
            : $this->resolveFromEmployeeNumber((int) $employeeNumber, $pin);
    }

    private function resolveFromBearerToken(string $bearerToken, ?int $employeeNumber): Employee
    {
        $employee = $this->userFromBearerToken($bearerToken)->employee;

        if ($employee === null) {
            throw ScanIdentityException::accountNotLinked();
        }

        if ($employeeNumber !== null && $employeeNumber !== (int) $employee->employee_number) {
            throw ScanIdentityException::employeeMismatch();
        }

        if ($employee->status !== EmployeeStatus::Active) {
            throw new InvalidScanCredentialsException(self::UNKNOWN_EMPLOYEE_MESSAGE);
        }

        return $employee;
    }

    private function resolveFromEmployeeNumber(int $employeeNumber, ?string $pin): Employee
    {
        $pinRequired = $this->scanPins->isRequired();
        $employee = $this->findScannableEmployee($employeeNumber);

        if ($employee === null) {
            throw $pinRequired
                ? ScanPinException::invalidCredentials()
                : new InvalidScanCredentialsException(self::UNKNOWN_EMPLOYEE_MESSAGE);
        }

        if ($pinRequired) {
            $this->scanPins->verifyForScan($employee, $pin);
        }

        return $employee;
    }

    /**
     * Non-active employees and disabled logins are indistinguishable from
     * an unknown number, so a QR holder walking employee numbers learns
     * nothing about the terminated-staff directory.
     */
    private function findScannableEmployee(int $employeeNumber): ?Employee
    {
        $employee = $this->employees->findActiveByNumber($employeeNumber);

        if ($employee?->user !== null && ! $employee->user->is_active) {
            return null;
        }

        return $employee;
    }

    /**
     * Looked up in the token table instead of auth('sanctum'): that guard
     * checks the session first, so a browser where an admin is signed in
     * could otherwise turn the public page into "scan as the admin".
     */
    private function userFromBearerToken(string $bearerToken): User
    {
        $accessToken = PersonalAccessToken::findToken($bearerToken);
        $user = $accessToken?->tokenable;

        if (! $user instanceof User || ! $user->is_active || $this->isExpired($accessToken)) {
            throw ScanIdentityException::invalidToken();
        }

        return $user;
    }

    private function isExpired(PersonalAccessToken $accessToken): bool
    {
        $lifetimeMinutes = (int) config('sanctum.expiration');

        if ($lifetimeMinutes > 0 && $accessToken->created_at->lte(now()->subMinutes($lifetimeMinutes))) {
            return true;
        }

        return $accessToken->expires_at !== null && $accessToken->expires_at->isPast();
    }
}
