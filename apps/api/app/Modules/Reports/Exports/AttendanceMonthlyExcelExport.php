<?php

declare(strict_types=1);

namespace App\Modules\Reports\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * XLSX export for the admin monthly attendance report.
 *
 * Mirrors the CSV layout exactly (same headings, same column order) so
 * downstream consumers can swap formats without rewriting parsers. The
 * only additions over CSV are visual: RTL sheet direction, a bold header
 * row, a frozen top row + first column, and number formatting for the
 * three minute-total columns.
 *
 * @implements FromCollection
 */
class AttendanceMonthlyExcelExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnFormatting,
    WithTitle,
    WithEvents
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly int $year,
        private readonly int $month,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'رقم الموظف',
            'الاسم',
            'القسم',
            'أيام الحضور',
            'أيام التأخير',
            'أيام الغياب',
            'أيام الإجازة',
            'إجمالي الدقائق',
            'دقائق الوقت الإضافي',
            'دقائق التأخير',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            (int) ($row['employee_number'] ?? 0),
            (string) ($row['full_name'] ?? ''),
            (string) ($row['department'] ?? ''),
            (int) ($row['present_days'] ?? 0),
            (int) ($row['late_days'] ?? 0),
            (int) ($row['absent_days'] ?? 0),
            (int) ($row['leave_days'] ?? 0),
            (int) ($row['total_minutes'] ?? 0),
            (int) ($row['overtime_minutes'] ?? 0),
            (int) ($row['late_minutes'] ?? 0),
        ];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2678C4'],
                ],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
        ];
    }

    /**
     * Format minute-total columns with a thousands separator so a
     * 12,480-minute row does not read as a bare "12480".
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    public function title(): string
    {
        // Excel sheet-title limit is 31 chars; this is 20.
        return sprintf('حضور-%04d-%02d', $this->year, $this->month);
    }

    /**
     * Applied to the sheet after headings + rows are written. RTL cannot
     * be expressed through the simpler concerns, so we hook the raw
     * worksheet here.
     *
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // RTL sheet — Arabic reads right-to-left, and PhpSpreadsheet
                // reflects that at the sheet view level so column A ends up
                // on the right edge in Excel.
                $sheet->setRightToLeft(true);

                // Freeze the header row + first column so long month
                // reports stay navigable when scrolling.
                $sheet->freezePane('B2');

                // Auto-size every used column — nicer than hard-coded
                // widths for varying Arabic name lengths.
                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Wrap long Arabic department names.
                $sheet->getStyle('C')->getAlignment()->setWrapText(true);
            },
        ];
    }
}
