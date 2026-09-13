<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\Request as RequestModel;
use App\Modules\Reports\Repositories\RequestReportRepository;
use App\Shared\Enums\RequestStatus;
use Generator;

class RequestReportService
{
    /**
     * @var list<string>
     */
    public const EXPORT_HEADER = [
        'رقم الطلب',
        'نوع الطلب',
        'رقم الموظف',
        'اسم الموظف',
        'القسم',
        'الحالة',
        'الخطوة الحالية',
        'تاريخ الإرسال',
        'تاريخ الإغلاق',
    ];

    private const DATETIME_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private readonly RequestReportRepository $repository,
    ) {}

    public function exportFilename(): string
    {
        return sprintf('requests-%s.csv', now()->format('Ymd'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<int|string|null>>
     */
    public function exportRows(array $filters): Generator
    {
        foreach ($this->repository->lazyForExport($filters) as $request) {
            yield $this->toRow($request);
        }
    }

    /**
     * @return list<int|string|null>
     */
    private function toRow(RequestModel $request): array
    {
        return [
            $request->request_number,
            $request->requestType?->name,
            $request->employee?->employee_number,
            $request->employee?->full_name,
            $request->employee?->department?->name,
            $this->statusLabel($request->status),
            $request->currentStep?->name,
            $request->submitted_at?->format(self::DATETIME_FORMAT),
            $request->completed_at?->format(self::DATETIME_FORMAT),
        ];
    }

    private function statusLabel(RequestStatus $status): string
    {
        return match ($status) {
            RequestStatus::Draft => 'مسودة',
            RequestStatus::Submitted => 'مُرسل',
            RequestStatus::Pending => 'قيد المراجعة',
            RequestStatus::Approved => 'موافق عليه',
            RequestStatus::Rejected => 'مرفوض',
            RequestStatus::Returned => 'مُعاد',
            RequestStatus::Cancelled => 'ملغى',
            RequestStatus::Completed => 'مكتمل',
        };
    }
}
