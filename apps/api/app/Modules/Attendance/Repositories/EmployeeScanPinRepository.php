<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Models\Employee;
use App\Models\EmployeeScanPin;
use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\ScanPinSource;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class EmployeeScanPinRepository
{
    public function findForEmployee(int $employeeId): ?EmployeeScanPin
    {
        return EmployeeScanPin::query()->where('employee_id', $employeeId)->first();
    }

    public function upsertForEmployee(int $employeeId, string $pinHash, ScanPinSource $setVia, ?int $setByUserId): void
    {
        EmployeeScanPin::query()->upsert(
            [[
                'employee_id' => $employeeId,
                'pin_hash' => $pinHash,
                'set_via' => $setVia->value,
                'set_by_user_id' => $setByUserId,
            ]],
            ['employee_id'],
            ['pin_hash', 'set_via', 'set_by_user_id'],
        );
    }

    /**
     * Insert-or-ignore rather than upsert: a PIN reset that lands while a
     * bulk run is in flight must win, otherwise the employee would get a
     * second SMS and the PIN the admin just handed over would stop working.
     *
     * @return bool true when this call created the row
     */
    public function createIfMissing(int $employeeId, string $pinHash, ScanPinSource $setVia, ?int $setByUserId): bool
    {
        $now = now();

        return EmployeeScanPin::query()->insertOrIgnore([
            'employee_id' => $employeeId,
            'pin_hash' => $pinHash,
            'set_via' => $setVia->value,
            'set_by_user_id' => $setByUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;
    }

    /**
     * @return array{active: int, with_pin: int, without_pin_and_phone: int}
     */
    public function activeEmployeeCoverage(): array
    {
        $row = $this->activeEmployees()
            ->leftJoin('employee_scan_pins', 'employee_scan_pins.employee_id', '=', 'employees.id')
            ->selectRaw('COUNT(*) AS active')
            ->selectRaw('COUNT(employee_scan_pins.id) AS with_pin')
            ->selectRaw(
                'SUM(CASE WHEN employee_scan_pins.id IS NULL AND (employees.phone IS NULL OR TRIM(employees.phone) = ?) THEN 1 ELSE 0 END) AS without_pin_and_phone',
                [''],
            )
            ->toBase()
            ->first();

        return [
            'active' => (int) ($row->active ?? 0),
            'with_pin' => (int) ($row->with_pin ?? 0),
            'without_pin_and_phone' => (int) ($row->without_pin_and_phone ?? 0),
        ];
    }

    public function countActiveEmployeesWithoutPin(): int
    {
        return $this->activeEmployees()->whereDoesntHave('scanPin')->count();
    }

    /**
     * chunkById (not chunk) because each batch inserts PINs and so shrinks
     * the filtered set; offset paging would silently skip employees.
     *
     * @param  Closure(\Illuminate\Support\Collection<int, Employee>): mixed  $callback
     */
    public function chunkActiveEmployeesWithoutPin(int $size, Closure $callback): void
    {
        $this->activeEmployees()
            ->whereDoesntHave('scanPin')
            ->chunkById($size, $callback);
    }

    /**
     * @return Builder<Employee>
     */
    private function activeEmployees(): Builder
    {
        return Employee::query()->staffOnly()->where('employees.status', EmployeeStatus::Active->value);
    }
}
