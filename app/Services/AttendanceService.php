<?php

namespace App\Services;

use App\DataObjects\FraudCheckContext;
use App\DataObjects\FraudCheckResult;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Repositories\AttendanceRepository;

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

    public function record(Employee $employee, FraudCheckContext $ctx): Attendance
    {
        $fraudResult = $this->fraud->check($ctx);
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
