<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Repositories;

use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LeaveTypeRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = LeaveType::query();

        $this->applyFilters($query, $filters);

        return $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);
    }

    public function findOrFail(int $id): LeaveType
    {
        return LeaveType::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LeaveType
    {
        return LeaveType::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LeaveType $leaveType, array $data): LeaveType
    {
        $leaveType->update($data);

        return $leaveType->refresh();
    }

    public function delete(LeaveType $leaveType): void
    {
        $leaveType->delete();
    }

    /**
     * @param  Builder<LeaveType>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['is_active', 'is_balance_based'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null && $filters[$field] !== '') {
                $query->where($field, filter_var($filters[$field], FILTER_VALIDATE_BOOLEAN));
            }
        }

        if (! empty($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $like = '%'.$filters['search'].'%';
                $q->where('name', 'like', $like)->orWhere('code', 'like', $like);
            });
        }
    }
}
