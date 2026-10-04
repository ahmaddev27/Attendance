<?php

declare(strict_types=1);

namespace App\Modules\Reports\Exports;

final class RequestsExcelExport extends TabularExcelExport
{
    public function title(): string
    {
        return 'الطلبات';
    }
}
