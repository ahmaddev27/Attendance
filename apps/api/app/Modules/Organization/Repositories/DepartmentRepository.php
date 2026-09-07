<?php

declare(strict_types=1);

namespace App\Modules\Organization\Repositories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class DepartmentRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Department>
     */
    public function list(array $filters): Collection
    {
        $query = Department::query()->with(['manager', 'parent']);

        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->get();
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
