<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Repositories;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LeaveBalanceRepository
{
    /**
     * @return Collection<int, LeaveBalance>
     */
    public function findByEmployee(int $employeeId, ?int $year = null): Collection
    {
        return LeaveBalance::query()
            ->with('leaveType')
            ->where('employee_id', $employeeId)
            ->when($year !== null, fn (Builder $query) => $query->where('year', $year))
            ->orderBy('leave_type_id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = LeaveBalance::query()->with(['employee', 'leaveType']);

        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (! empty($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        return $query->orderBy('employee_id')->paginate($perPage);
    }

    /**
     * Fetch the (employee, leave_type, year) balance row, creating it on
     * first access. New rows default to the leave type's
     * default_annual_entitlement unless an explicit entitlement/carry-over
     * is supplied (used by LeaveBalanceService::accrueForYear, which
     * already knows both figures).
     *
     * $lockForUpdate must be true whenever the caller is about to mutate
     * used/pending inside a transaction (submit/approve/reject/cancel) so
     * two concurrent requests against the same balance row serialize
     * instead of racing on a read-then-write.
     */
    public function getOrCreateForYear(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        bool $lockForUpdate = false,
        ?float $entitlement = null,
        float $carryOver = 0.0,
    ): LeaveBalance {
        $query = LeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $balance = $query->first();

        if ($balance !== null) {
            return $balance;
        }

        return LeaveBalance::query()->create([
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId,
            'year' => $year,
            'entitlement' => $entitlement ?? (float) (LeaveType::query()->whereKey($leaveTypeId)->value('default_annual_entitlement') ?? 0),
            'carry_over_from_previous' => $carryOver,
        ]);
    }
}
