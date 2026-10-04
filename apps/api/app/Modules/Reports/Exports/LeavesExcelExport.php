<?php

declare(strict_types=1);

namespace App\Modules\Reports\Exports;

final class LeavesExcelExport extends TabularExcelExport
{
    public function title(): string
    {
        return 'الإجازات';
    }
}
