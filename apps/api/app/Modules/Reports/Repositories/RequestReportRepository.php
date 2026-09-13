<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Models\Employee;
use App\Models\Request as RequestModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

class RequestReportRepository
{
    private const CHUNK_SIZE = 500;

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, RequestModel>
     */
    public function lazyForExport(array $filters): LazyCollection
    {
        $query = RequestModel::query()->with([
            // A report is history: a soft-deleted employee, department or
            // request type must still be named on the rows it owns.
            'employee' => fn (BelongsTo $relation) => $relation->withTrashed(),
            'employee.department' => fn (BelongsTo $relation) => $relation->withTrashed(),
            'requestType' => fn (BelongsTo $relation) => $relation->withTrashed(),
            'currentStep',
        ]);

        $this->applyFilters($query, $filters);

        return $query->lazyById(self::CHUNK_SIZE);
    }

    /**
     * @param  Builder<RequestModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['request_type_id', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Bare comparisons keep any index on submitted_at usable; "to" is
        // inclusive for the user, so the bound is the next midnight, exclusive.
        if (! empty($filters['from'])) {
            $query->where('submitted_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('submitted_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        if (! empty($filters['department_id'])) {
            $query->whereIn(
                'employee_id',
                Employee::withTrashed()->select('id')->where('department_id', $filters['department_id']),
            );
        }
    }
}
