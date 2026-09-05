<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Repositories\LeaveRequestRepository;
use App\Services\Sms\SmsService;
use DomainException;

class LeaveService
{
    public function __construct(
        private readonly LeaveRequestRepository $repo,
        private readonly SmsService $sms,
    ) {}

    public function submit(Employee $employee, array $data): LeaveRequest
    {
        $start = $data['start_date'];
        $end = $data['end_date'] ?? $data['start_date'];

        $this->assertNoOverlap($employee, $start, $end);

        return $this->repo->create($employee, [
            'start_date' => $start,
            'end_date' => $end,
            'note' => $data['note'] ?? null,
            'status' => LeaveStatus::Pending,
        ]);
    }

    public function approve(LeaveRequest $leave, User $reviewer): LeaveRequest
    {
        $this->assertPending($leave);

        $updated = $this->repo->update($leave, [
            'status' => LeaveStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->sms->dispatch(
            $updated->employee->phone,
            trans('messages.leave_approved', ['date' => $updated->start_date->format('Y-m-d')])
        );

        return $updated;
    }

    public function reject(LeaveRequest $leave, User $reviewer, string $reason): LeaveRequest
    {
        $this->assertPending($leave);

        $updated = $this->repo->update($leave, [
            'status' => LeaveStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->sms->dispatch(
            $updated->employee->phone,
            trans('messages.leave_rejected', [
                'date' => $updated->start_date->format('Y-m-d'),
                'reason' => $reason,
            ])
        );

        return $updated;
    }

    private function assertPending(LeaveRequest $leave): void
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw new DomainException('Only pending leaves can be decided.');
        }
    }

    private function assertNoOverlap(Employee $employee, string $start, string $end): void
    {
        $overlapping = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->exists();

        if ($overlapping) {
            throw new DomainException('لديك طلب إجازة موجود مسبقاً في هذه الفترة');
        }
    }
}
