<?php

declare(strict_types=1);

namespace App\Modules\Reports\Exports;

final class RequestsPdfView extends TabularPdfView
{
    public function title(): string
    {
        return 'تقرير الطلبات';
    }
}
