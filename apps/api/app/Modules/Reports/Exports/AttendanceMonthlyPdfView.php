<?php

declare(strict_types=1);

namespace App\Modules\Reports\Exports;

use Illuminate\Support\Collection;

/**
 * Plain data-holder passed to the `exports.attendance-monthly` Blade view.
 *
 * Keeping this as a typed value object (instead of an assoc-array) means
 * the Blade template gets IDE hints and PHPStan can catch a rename of any
 * report row key.
 */
final class AttendanceMonthlyPdfView
{
    /**
     * @param  Collection<int, array{
     *   employee_id: int,
     *   employee_number: int,
     *   full_name: string,
     *   department: ?string,
     *   present_days: int,
     *   late_days: int,
     *   absent_days: int,
     *   leave_days: int,
     *   total_minutes: int,
     *   overtime_minutes: int,
     *   late_minutes: int,
     * }>  $rows
     */
    public function __construct(
        public readonly Collection $rows,
        public readonly int $year,
        public readonly int $month,
        public readonly string $generatedAt,
    ) {}

    /**
     * Row totals shown in the PDF footer — mirrors what the frontend
     * "totals strip" displays so a printed copy tells the same story.
     *
     * @return array<string, int>
     */
    public function totals(): array
    {
        return [
            'present_days' => (int) $this->rows->sum('present_days'),
            'late_days' => (int) $this->rows->sum('late_days'),
            'absent_days' => (int) $this->rows->sum('absent_days'),
            'leave_days' => (int) $this->rows->sum('leave_days'),
            'total_minutes' => (int) $this->rows->sum('total_minutes'),
            'overtime_minutes' => (int) $this->rows->sum('overtime_minutes'),
            'late_minutes' => (int) $this->rows->sum('late_minutes'),
        ];
    }

    /**
     * Localised Arabic month name for the report header.
     */
    public function monthLabel(): string
    {
        $names = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        return $names[$this->month] ?? (string) $this->month;
    }
}
