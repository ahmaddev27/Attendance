<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Repositories;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

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
        // SELECT ... FOR UPDATE does NOT gap-lock a missing row on MySQL's
        // default REPEATABLE READ, so two concurrent first-time submissions
        // used to both fall through to the create() branch — one won, the
        // other blew up with a 23000 duplicate-key error → 500. The retry
        // loop below handles that race: on the second pass the losing
        // thread finds the row the winner just committed and returns it.
        $attempt = 0;

        while (true) {
            $balance = $this->firstForUpdate($employeeId, $leaveTypeId, $year, $lockForUpdate);

            if ($balance !== null) {
                return $balance;
            }

            try {
                return LeaveBalance::query()->firstOrCreate(
                    [
                        'employee_id' => $employeeId,
                        'leave_type_id' => $leaveTypeId,
                        'year' => $year,
                    ],
                    [
                        'entitlement' => $entitlement ?? (float) (LeaveType::query()->whereKey($leaveTypeId)->value('default_annual_entitlement') ?? 0),
                        'carry_over_from_previous' => $carryOver,
                    ],
                );
            } catch (QueryException $exception) {
                // Integrity constraint violation — someone else won the race
                // between our SELECT and our INSERT. Retry once so we pick
                // up the row they just committed. Second failure escalates.
                if ($attempt >= 1 || $exception->getCode() !== '23000') {
                    throw $exception;
                }

                $attempt++;
            }
        }
    }

    private function firstForUpdate(int $employeeId, int $leaveTypeId, int $year, bool $lockForUpdate): ?LeaveBalance
    {
        $query = LeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
