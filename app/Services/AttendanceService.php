<?php

namespace App\Services;

use App\DataObjects\FraudCheckContext;
use App\DataObjects\FraudCheckResult;
use App\Enums\AttendanceType;
use App\Enums\FraudCheckStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Repositories\AttendanceRepository;
use DomainException;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepository $repo,
        private readonly FraudGuardService $fraud,
    ) {}

    public function previewNextType(Employee $employee): AttendanceType
    {
        $last = $this->repo->lastForEmployeeToday($employee);

        if (! $last) {
            return AttendanceType::CheckIn;
        }

        return $last->type === AttendanceType::CheckIn
            ? AttendanceType::CheckOut
            : AttendanceType::CheckIn;
    }

    /**
     * Records an attendance scan after enforcing the fraud check.
     *
     * @throws DomainException when the fraud check fails, carrying the
     *                          failing FraudCheckStatus value as its message
     *                          so callers can translate it into a user-facing error.
     */
    public function record(Employee $employee, FraudCheckContext $ctx): Attendance
    {
        $fraudResult = $this->fraud->check($ctx);

        if (! $fraudResult->passed) {
            throw new DomainException(
                $fraudResult->status->value,
                match ($fraudResult->status) {
                    FraudCheckStatus::GpsFailed => 1,
                    FraudCheckStatus::IpFailed => 2,
                    default => 0,
                }
            );
        }

        $type = $this->previewNextType($employee);

        return $this->repo->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'scanned_at' => now(),
            'ip_address' => $ctx->ip,
            'latitude' => $ctx->latitude,
            'longitude' => $ctx->longitude,
            'fraud_check_status' => $fraudResult->status,
        ]);
    }

    public function checkFraud(FraudCheckContext $ctx): FraudCheckResult
    {
        return $this->fraud->check($ctx);
    }
}
