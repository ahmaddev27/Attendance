<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Exporters;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportExporter
{
    public function __construct(
        private readonly CsvExporter $csv,
    ) {}

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'employee_number', 'label' => 'Employee Number'],
            ['key' => 'employee_name', 'label' => 'Employee Name'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'check_in', 'label' => 'Check In'],
            ['key' => 'check_out', 'label' => 'Check Out'],
            ['key' => 'work_hours', 'label' => 'Work Hours'],
            ['key' => 'late_minutes', 'label' => 'Late Minutes'],
            ['key' => 'early_leave_minutes', 'label' => 'Early Leave Minutes'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function export(Collection $rows): StreamedResponse
    {
        return $this->csv->stream($rows, $this->columns(), 'attendance-report');
    }
}
