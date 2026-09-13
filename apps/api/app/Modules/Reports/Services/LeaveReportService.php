<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\LeaveRequest;
use App\Modules\Reports\Repositories\LeaveReportRepository;
use App\Shared\Enums\LeaveStatus;
use Generator;

class LeaveReportService
{
    /**
     * @var list<string>
     */
    public const EXPORT_HEADER = [
        'رقم الطلب',
        'رقم الموظف',
        'اسم الموظف',
        'القسم',
        'نوع الإجازة',
        'تاريخ البداية',
        'تاريخ النهاية',
        'عدد الأيام',
        'الحالة',
        'تاريخ التقديم',
        'تاريخ القرار',
        'صاحب القرار',
    ];

    private const DATETIME_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private readonly LeaveReportRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportFilename(array $filters): string
    {
        return sprintf('leaves-%04d.csv', $this->year($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<int|float|string|null>>
     */
    public function exportRows(array $filters): Generator
    {
        foreach ($this->repository->lazyForExport($this->year($filters), $filters) as $leave) {
            yield $this->toRow($leave);
        }
    }

    /**
     * @return list<int|float|string|null>
     */
    private function toRow(LeaveRequest $leave): array
    {
        return [
            $leave->id,
            $leave->employee?->employee_number,
            $leave->employee?->full_name,
            $leave->employee?->department?->name,
            $leave->leaveType?->name,
            $leave->start_date?->toDateString(),
            $leave->end_date?->toDateString(),
            (float) $leave->days,
            $this->statusLabel($leave->status),
            $leave->created_at?->format(self::DATETIME_FORMAT),
            $leave->reviewed_at?->format(self::DATETIME_FORMAT),
            $leave->reviewer?->name,
        ];
    }

    private function statusLabel(LeaveStatus $status): string
    {
        return match ($status) {
            LeaveStatus::Draft => 'مسودة',
            LeaveStatus::Pending => 'قيد المراجعة',
            LeaveStatus::Approved => 'موافق عليها',
            LeaveStatus::Rejected => 'مرفوضة',
            LeaveStatus::Cancelled => 'ملغاة',
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function year(array $filters): int
    {
        return (int) ($filters['year'] ?? now()->year);
    }
}
