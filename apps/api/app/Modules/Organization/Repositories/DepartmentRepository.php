<?php

declare(strict_types=1);

namespace App\Modules\Organization\Repositories;

use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Department>
     */
    public function list(array $filters): Collection
    {
        return $this->query($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder<Department>
     */
    private function query(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        // withCount powers the `employees_count` column in the admin list —
        // one COUNT(*) subselect per department, no N+1. Same on the Team
        // repo below.
        $query = Department::query()
            ->with(['manager', 'parent'])
            ->withCount('employees');

        // Legacy `active` alias — kept for callers that still pass the
        // old key. The web + tests use `is_active` now.
        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name');
    }

    public function findOrFail(int $id): Department
    {
        return Department::query()->with(['manager', 'parent', 'children'])->findOrFail($id);
    }

    /**
     * Fetch just the parent_id column, without hydrating a full model.
     * Used by DepartmentService's circular-reference walk.
     */
    public function findParentId(int $id): ?int
    {
        $parentId = Department::query()->whereKey($id)->value('parent_id');

        return $parentId === null ? null : (int) $parentId;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Department
    {
        return Department::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Department $department, array $attributes): Department
    {
        $department->update($attributes);

        return $department->refresh();
    }

    public function delete(Department $department): bool
    {
        return (bool) $department->delete();
    }
}
