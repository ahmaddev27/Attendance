<?php

declare(strict_types=1);

namespace App\Modules\Reports\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Shared RTL sheet for the row-per-record admin reports.
 *
 * Rows arrive already shaped by the report service (same cells, same order
 * as the CSV), so a consumer can swap formats without rewriting a parser.
 */
abstract class TabularExcelExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithEvents
{
    /**
     * @param  list<string>  $headings
     * @param  Collection<int, list<int|float|string|null>>  $rows
     */
    public function __construct(
        private readonly array $headings,
        private readonly Collection $rows,
    ) {}

    /**
     * @return Collection<int, list<int|float|string|null>>
     */
    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2678C4']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Sheet direction is a worksheet-view property that none of
                // the simpler concerns expose.
                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                $lastColumn = $sheet->getHighestColumn();
                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }
}
