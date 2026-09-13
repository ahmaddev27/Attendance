<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\LazyCollection;

class LeaveReportRepository
{
    private const CHUNK_SIZE = 500;

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, LeaveRequest>
     */
    public function lazyForExport(int $year, array $filters): LazyCollection
    {
        $query = LeaveRequest::query()->with([
            // A report is history: a soft-deleted employee, department or
            // leave type must still be named on the rows it owns.
            'employee' => fn (BelongsTo $relation) => $relation->withTrashed(),
            'employee.department' => fn (BelongsTo $relation) => $relation->withTrashed(),
            'leaveType' => fn (BelongsTo $relation) => $relation->withTrashed(),
            'reviewer',
        ]);

        $this->applyFilters($query, $year, $filters);

        return $query->lazyById(self::CHUNK_SIZE);
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, int $year, array $filters): void
    {
        // Overlap test on bare DATE columns so the (start_date, end_date)
        // index stays usable. The upper bound is half-open because SQLite
        // stores date casts with a midnight time part, which a
        // "<= 31 December" string comparison would silently drop.
        $query->where('start_date', '<', sprintf('%04d-01-01', $year + 1))
            ->where('end_date', '>=', sprintf('%04d-01-01', $year));

        foreach (['leave_type_id', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['department_id'])) {
            $query->whereIn(
                'employee_id',
                Employee::withTrashed()->select('id')->where('department_id', $filters['department_id']),
            );
        }
    }
}
