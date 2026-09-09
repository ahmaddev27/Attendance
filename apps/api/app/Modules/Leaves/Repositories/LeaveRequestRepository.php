<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Repositories;

use App\Models\LeaveRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LeaveRequestRepository
{
    /**
     * Relations eager-loaded on every read so the resource layer never
     * triggers an N+1 query per row.
     *
     * @var list<string>
     */
    private const WITH = ['employee', 'leaveType', 'reviewer'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = LeaveRequest::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('start_date')->paginate($perPage);
    }

    public function findOrFail(int $id): LeaveRequest
    {
        return LeaveRequest::query()->with(self::WITH)->findOrFail($id);
    }

    /**
     * Re-fetches the request with a row lock, for use inside a transaction
     * that is about to transition its status (approve/reject/cancel) — see
     * AttendanceService for the same lockForUpdate-inside-transaction
     * pattern guarding against a concurrent double-decision race.
     */
    public function findForUpdate(int $id): LeaveRequest
    {
        return LeaveRequest::query()->with(self::WITH)->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LeaveRequest
    {
        return LeaveRequest::query()->create($data)->load(self::WITH);
    }

    public function delete(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->delete();
    }

    public function hasOverlapping(int $employeeId, \DateTimeInterface|string $start, \DateTimeInterface|string $end, bool $lockForUpdate = false): bool
    {
        $query = LeaveRequest::query()->overlapping($employeeId, $start, $end);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['employee_id', 'leave_type_id', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Both columns are true DATE — bare where() so the
        // (start_date, end_date) composite index actually gets used.
        if (! empty($filters['start_date'])) {
            $query->where('end_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('start_date', '<=', $filters['end_date']);
        }
    }
}
