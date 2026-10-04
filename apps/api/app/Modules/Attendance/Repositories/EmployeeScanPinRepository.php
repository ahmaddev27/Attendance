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

    /**
     * Resolve the row whose lookup hash matches — this is the PIN-only
     * identity path. Returns null when nothing matches, so the caller can
     * translate that to "invalid credentials" without leaking existence.
     */
    public function findByLookupHash(string $lookupHash): ?EmployeeScanPin
    {
        return EmployeeScanPin::query()->where('pin_lookup_hash', $lookupHash)->first();
    }

    /**
     * Used by the generator's uniqueness loop. Excludes a specific
     * employee so a reset for someone who already owns that PIN does not
     * count as a conflict with themselves.
     */
    public function isLookupHashTaken(string $lookupHash, ?int $excludeEmployeeId = null): bool
    {
        return EmployeeScanPin::query()
            ->where('pin_lookup_hash', $lookupHash)
            ->when($excludeEmployeeId !== null, fn ($q) => $q->where('employee_id', '!=', $excludeEmployeeId))
            ->exists();
    }

    /**
     * Legacy-row fallback for PIN-only identity: bcrypt-scan the (small)
     * set of rows that pre-date the HMAC lookup column — those are the
     * only rows that could pass the HMAC check and still match a typed
     * PIN. Returns the first match or null. O(n) bcrypt per unresolved
     * scan, but once the lookup hash is backfilled the scan short-circuits.
     */
    public function findByBcryptScan(string $pin): ?EmployeeScanPin
    {
        $pins = EmployeeScanPin::query()
            ->whereNull('pin_lookup_hash')
            ->get();

        foreach ($pins as $scanPin) {
            if (\Illuminate\Support\Facades\Hash::check($pin, $scanPin->pin_hash)) {
                return $scanPin;
            }
        }

        return null;
    }

    /**
     * Fills in the HMAC lookup hash on a legacy row discovered via
     * `findByBcryptScan`. Uses `updateQuietly` to avoid firing the model's
     * saved event (which would otherwise write a `pin_hash` activity log
     * entry on every first-use of a legacy PIN).
     */
    public function backfillLookupHash(EmployeeScanPin $scanPin, string $lookupHash): void
    {
        $scanPin->forceFill(['pin_lookup_hash' => $lookupHash])->saveQuietly();
    }

    public function upsertForEmployee(int $employeeId, string $pinHash, string $lookupHash, ScanPinSource $setVia, ?int $setByUserId): void
    {
        EmployeeScanPin::query()->upsert(
            [[
                'employee_id' => $employeeId,
                'pin_hash' => $pinHash,
                'pin_lookup_hash' => $lookupHash,
                'set_via' => $setVia->value,
                'set_by_user_id' => $setByUserId,
            ]],
            ['employee_id'],
            ['pin_hash', 'pin_lookup_hash', 'set_via', 'set_by_user_id'],
        );
    }

    /**
     * Insert-or-ignore rather than upsert: a PIN reset that lands while a
     * bulk run is in flight must win, otherwise the employee would get a
     * second SMS and the PIN the admin just handed over would stop working.
     *
     * @return bool true when this call created the row
     */
    public function createIfMissing(int $employeeId, string $pinHash, string $lookupHash, ScanPinSource $setVia, ?int $setByUserId): bool
    {
        $now = now();

        return EmployeeScanPin::query()->insertOrIgnore([
            'employee_id' => $employeeId,
            'pin_hash' => $pinHash,
            'pin_lookup_hash' => $lookupHash,
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
