<?php

namespace App\Repositories;

use App\Models\Attendance;
use App\Models\Employee;

class AttendanceRepository
{
    public function lastForEmployeeToday(Employee $employee): ?Attendance
    {
        return Attendance::where('employee_id', $employee->id)
            ->whereDate('scanned_at', today())
            ->latest('scanned_at')
            ->first();
    }

    public function create(array $data): Attendance
    {
        return Attendance::create($data);
    }
}
