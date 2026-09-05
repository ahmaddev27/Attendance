<?php

namespace App\Services;

use App\Models\Employee;
use App\Repositories\EmployeeRepository;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepository $repo,
        private readonly SettingsService $settings,
        private readonly SmsService $sms,
    ) {}

    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $data['employee_number'] = $this->nextEmployeeNumber();
            $employee = $this->repo->create($data);

            $this->sms->dispatch(
                $employee->phone,
                trans('messages.employee_welcome', [
                    'company' => config('app.name'),
                    'number' => $employee->employee_number,
                ])
            );

            return $employee;
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return $this->repo->update($employee, $data);
    }

    private function nextEmployeeNumber(): int
    {
        $max = $this->repo->maxEmployeeNumber();
        if ($max > 0) return $max + 1;

        return (int) $this->settings->get('employee_number_start', 1001);
    }
}
