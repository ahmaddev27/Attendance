<?php

namespace App\Repositories;

use App\Models\Employee;
use App\Models\LeaveRequest;

class LeaveRequestRepository
{
    public function create(Employee $employee, array $data): LeaveRequest
    {
        return $employee->leaveRequests()->create($data);
    }

    public function update(LeaveRequest $leave, array $data): LeaveRequest
    {
        $leave->update($data);

        return $leave->fresh();
    }
}
