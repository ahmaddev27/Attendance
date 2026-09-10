<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Modules\Leaves\Repositories\LeaveBalanceRepository;
use App\Shared\Enums\LeaveStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveBalanceService
{
    public function __construct(
        private readonly LeaveBalanceRepository $balances,
    ) {}

    /**
     * @return Collection<int, LeaveBalance>
     */
    public function findByEmployee(int $employeeId, ?int $year = null): Collection
    {
        return $this->balances->findByEmployee($employeeId, $year);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->balances->paginate($filters, $perPage);
    }

    /**
     * Creates the current year's balance row for every active,
     * balance-based leave type the employee doesn't already have one for.
     * Carry-over from the previous year is left at 0 for M4 — no cap/rule
     * has been defined yet for how much can roll forward.
     *
     * @return Collection<int, LeaveBalance>
     */
    public function accrueForYear(Employee $employee, int $year): Collection
    {
        return DB::transaction(function () use ($employee, $year) {
            return LeaveType::query()
                ->active()
                ->balanceBased()
                ->get()
                ->map(fn (LeaveType $type) => $this->balances->getOrCreateForYear(
                    employeeId: $employee->id,
                    leaveTypeId: $type->id,
                    year: $year,
                    entitlement: (float) $type->default_annual_entitlement,
                    carryOver: 0.0,
                ));
        });
    }

    /**
     * Admin correction to an employee's entitlement for a given year (e.g.
     * a manual grant of extra days, or a fix for a data-entry mistake).
     * Deliberately adjusts `entitlement` rather than `used`/`pending` —
     * those two are derived from actual LeaveRequest state and must stay
     * in sync with it (see recomputeFromApprovedRequests()).
     */
    public function adjust(int $employeeId, int $leaveTypeId, int $year, float $delta, string $reason): LeaveBalance
    {
        return DB::transaction(function () use ($employeeId, $leaveTypeId, $year, $delta, $reason) {
            $balance = $this->balances->getOrCreateForYear($employeeId, $leaveTypeId, $year, lockForUpdate: true);

            $balance->entitlement = (float) $balance->entitlement + $delta;
            $balance->save();

            Log::info('Leave balance adjusted', [
                'leave_balance_id' => $balance->id,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
                'delta' => $delta,
                'reason' => $reason,
            ]);

            // Persist a structured audit trail in the activity_log table
            // (spatie/laravel-activitylog). Log::info is fine for ops
            // visibility, but a granted-days event MUST land in the same
            // durable, filterable audit stream that the compliance UI
            // reads — otherwise an HR user with `approve-leaves` can hand
            // out arbitrary balance with no in-app trace.
            activity('leave-balance')
                ->causedBy(auth()->user())
                ->performedOn($balance)
                ->withProperties([
                    'delta' => $delta,
                    'reason' => $reason,
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'year' => $year,
                ])
                ->log('balance_adjusted');

            return $balance->refresh();
        });
    }

    /**
     * Recomputes `used`/`pending` for one (employee, leave type, year)
     * balance from the actual LeaveRequest rows, guarding against drift
     * from a past bug or a manual database edit.
     */
    public function recomputeFromApprovedRequests(Employee $employee, LeaveType $leaveType, int $year): LeaveBalance
    {
        return DB::transaction(function () use ($employee, $leaveType, $year) {
            $balance = $this->balances->getOrCreateForYear($employee->id, $leaveType->id, $year, lockForUpdate: true);

            // Bare where() range on the DATE column so an index on
            // (employee_id, start_date) can be used — whereYear() wraps the
            // column in YEAR(...) and disqualifies any such index.
            $baseQuery = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('start_date', '>=', "{$year}-01-01")
                ->where('start_date', '<', ($year + 1) . '-01-01');

            $balance->used = (clone $baseQuery)->where('status', LeaveStatus::Approved)->sum('days');
            $balance->pending = (clone $baseQuery)->where('status', LeaveStatus::Pending)->sum('days');
            $balance->save();

            return $balance->refresh();
        });
    }
}
