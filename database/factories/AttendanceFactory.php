<?php

namespace Database\Factories;

use App\Enums\AttendanceType;
use App\Enums\FraudCheckStatus;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => AttendanceType::CheckIn,
            'scanned_at' => now(),
            'fraud_check_status' => FraudCheckStatus::Skipped,
        ];
    }
}
