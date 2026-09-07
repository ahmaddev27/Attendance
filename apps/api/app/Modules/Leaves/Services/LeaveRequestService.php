<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Modules\Leaves\Repositories\LeaveBalanceRepository;
use App\Modules\Leaves\Repositories\LeaveRequestRepository;
use App\Modules\Leaves\Repositories\LeaveTypeRepository;
use App\Shared\Enums\LeaveStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the leave request lifecycle (submit -> approve/reject, or
 * cancel from any non-final state), including the balance-reservation
 * bookkeeping described in the M4 spec: pending is reserved on submit,
 * moved to used on approval, and released back on reject/cancel.
 *
 * Every state-changing method runs inside a DB transaction and re-reads
 * the rows it mutates with lockForUpdate(), the same pattern
 * AttendanceService uses for the check-in/check-out race — two
 * near-simultaneous actions on the same request or balance (e.g. an
 * admin double-clicking "approve", or two submissions racing for the
 * last remaining balance day) must serialize rather than both succeed.
 */
class LeaveRequestService
{
    public function __construct(
        private readonly LeaveRequestRepository $requests,
        private readonly LeaveTypeRepository $leaveTypes,
        private readonly LeaveBalanceRepository $balances,
        private readonly LeaveWorkingDaysCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listAdmin(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->requests->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listForEmployee(Employee $employee, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->requests->paginate([...$filters, 'employee_id' => $employee->id], $perPage);
    }

    public function find(int $id): LeaveRequest
    {
        return $this->requests->findOrFail($id);
    }

    /**
     * Working-day count a [start, end] range would consume for the given
     * employee — exposed standalone so a future "preview before you
     * submit" UI can call it without going through submit()'s side
     * effects.
     */
    public function computeDaysFor(Employee $employee, Carbon $start, Carbon $end): float
    {
        return $this->calculator->compute($employee, $start, $end);
    }

    /**
     * @param  array<string, mixed>  $data  leave_type_id, start_date, end_date, reason?, attachment_path?
     */
    public function submit(Employee $employee, array $data): LeaveRequest
    {
        $leaveType = $this->findActiveLeaveType((int) $data['leave_type_id']);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->startOfDay();

        $this->assertMinNotice($leaveType, $startDate);
        $this->assertEndNotBeforeStart($startDate, $endDate);

        $days = $this->calculator->compute($employee, $startDate, $endDate);

        $this->assertWithinMaxConsecutiveDays($leaveType, $startDate, $endDate);

        if (empty($data['attachment_path']) && $leaveType->requires_attachment) {
            throw ValidationException::withMessages([
                'attachment_path' => 'This leave type requires a supporting attachment.',
            ]);
        }

        return DB::transaction(function () use ($employee, $leaveType, $startDate, $endDate, $days, $data) {
            // Locked inside the transaction so a second, overlapping
            // submission racing this one sees it (or is seen by it) before
            // either commits.
            if ($this->requests->hasOverlapping($employee->id, $startDate, $endDate, lockForUpdate: true)) {
                throw ValidationException::withMessages([
                    'start_date' => 'This employee already has a pending or approved leave request overlapping these dates.',
                ]);
            }

            if ($leaveType->is_balance_based) {
                $balance = $this->balances->getOrCreateForYear($employee->id, $leaveType->id, $startDate->year, lockForUpdate: true);

                if ($balance->available < $days) {
                    throw ValidationException::withMessages([
                        'days' => 'Insufficient leave balance for this request.',
                    ]);
                }

                $balance->increment('pending', $days);
            }

            return $this->requests->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'reason' => $data['reason'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => LeaveStatus::Pending,
            ]);
        });
    }

    public function approve(LeaveRequest $leaveRequest, User $admin): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $admin) {
            $locked = $this->requests->findForUpdate($leaveRequest->id);

            if (! $locked->canBeApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending leave requests can be approved.',
                ]);
            }

            $locked->update([
                'status' => LeaveStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if ($locked->leaveType->is_balance_based) {
                $balance = $this->balances->getOrCreateForYear($locked->employee_id, $locked->leave_type_id, $locked->start_date->year, lockForUpdate: true);
                $balance->decrement('pending', $locked->days);
                $balance->increment('used', $locked->days);
            }

            return $locked->fresh(['employee', 'leaveType', 'reviewer']);
        });
    }

    public function reject(LeaveRequest $leaveRequest, User $admin, string $rejectionReason): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $admin, $rejectionReason) {
            $locked = $this->requests->findForUpdate($leaveRequest->id);

            if (! $locked->canBeRejected()) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending leave requests can be rejected.',
                ]);
            }

            $locked->update([
                'status' => LeaveStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            if ($locked->leaveType->is_balance_based) {
                $balance = $this->balances->getOrCreateForYear($locked->employee_id, $locked->leave_type_id, $locked->start_date->year, lockForUpdate: true);
                $balance->decrement('pending', $locked->days);
            }

            return $locked->fresh(['employee', 'leaveType', 'reviewer']);
        });
    }

    /**
     * Cancels a draft, pending, or approved request, releasing whichever
     * balance bucket it was holding (pending for draft/pending, used for
     * approved).
     *
     * An employee cancelling their own already-approved leave is only
     * allowed once the same notice window their leave type required at
     * submission time (min_notice_days) still applies before start_date —
     * the spec calls for "within N days notice" without naming N
     * explicitly, so this reuses the leave type's own notice policy
     * rather than introducing a second, unrelated constant. An admin
     * ($isAdmin = true) bypasses this check entirely.
     */
    public function cancel(LeaveRequest $leaveRequest, bool $isAdmin): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $isAdmin) {
            $locked = $this->requests->findForUpdate($leaveRequest->id);

            if (! $locked->canBeCancelled()) {
                throw ValidationException::withMessages([
                    'status' => 'This leave request can no longer be cancelled.',
                ]);
            }

            if (! $isAdmin && $locked->status === LeaveStatus::Approved) {
                $this->assertMinNotice($locked->leaveType, $locked->start_date, field: 'start_date', action: 'cancelled');
            }

            $previousStatus = $locked->status;

            $locked->update(['status' => LeaveStatus::Cancelled]);

            if ($locked->leaveType->is_balance_based) {
                $balance = $this->balances->getOrCreateForYear($locked->employee_id, $locked->leave_type_id, $locked->start_date->year, lockForUpdate: true);

                if ($previousStatus === LeaveStatus::Approved) {
                    $balance->decrement('used', $locked->days);
                } else {
                    $balance->decrement('pending', $locked->days);
                }
            }

            return $locked->fresh(['employee', 'leaveType', 'reviewer']);
        });
    }

    /**
     * Permanently removes a request that was never submitted past draft.
     * Anything already pending/approved/etc. must go through cancel()
     * instead, since only that path releases the balance it reserved.
     */
    public function deleteDraft(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->status !== LeaveStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft leave requests can be deleted — cancel a submitted request instead.',
            ]);
        }

        $this->requests->delete($leaveRequest);
    }

    private function findActiveLeaveType(int $leaveTypeId): LeaveType
    {
        $leaveType = $this->leaveTypes->findOrFail($leaveTypeId);

        if (! $leaveType->is_active) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'This leave type is not currently active.',
            ]);
        }

        return $leaveType;
    }

    private function assertMinNotice(LeaveType $leaveType, Carbon $startDate, string $field = 'start_date', string $action = 'requested'): void
    {
        $noticeDays = $leaveType->min_notice_days;
        $earliestAllowed = Carbon::today()->addDays($noticeDays);

        if ($startDate->lt($earliestAllowed)) {
            throw ValidationException::withMessages([
                $field => "This leave type requires at least {$noticeDays} day(s) notice before it can be {$action}.",
            ]);
        }
    }

    private function assertEndNotBeforeStart(Carbon $startDate, Carbon $endDate): void
    {
        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date cannot be before the start date.',
            ]);
        }
    }

    /**
     * max_consecutive_days caps the calendar span of a single request
     * (start to end inclusive), not the working-day count — a "no more
     * than 14 days off at a stretch" policy is meant to include any
     * weekends that fall inside the stretch, not just the days that would
     * actually be deducted from the balance.
     */
    private function assertWithinMaxConsecutiveDays(LeaveType $leaveType, Carbon $startDate, Carbon $endDate): void
    {
        if ($leaveType->max_consecutive_days === null) {
            return;
        }

        $consecutiveDays = $startDate->diffInDays($endDate) + 1;

        if ($consecutiveDays > $leaveType->max_consecutive_days) {
            throw ValidationException::withMessages([
                'end_date' => "This leave type cannot be requested for more than {$leaveType->max_consecutive_days} consecutive day(s).",
            ]);
        }
    }
}
