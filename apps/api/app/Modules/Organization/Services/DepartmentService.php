<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Modules\Organization\Repositories\DepartmentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    public function __construct(
        private readonly DepartmentRepository $departments,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Department>
     */
    public function list(array $filters): Collection
    {
        return $this->departments->list($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->departments->paginate($filters, $perPage);
    }

    public function find(int $id): Department
    {
        return $this->departments->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department
    {
        // Companies are single-row in Phase 1 — default to it when the
        // caller doesn't specify one explicitly.
        $data['company_id'] ??= Company::query()->value('id');

        if (! empty($data['parent_id'])) {
            $this->assertParentAssignmentIsNotCircular(null, (int) $data['parent_id']);
        }

        return $this->departments->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->assertParentAssignmentIsNotCircular($department, (int) $data['parent_id']);
        }

        return $this->departments->update($department, $data);
    }

    public function delete(Department $department): bool
    {
        // Refuse to delete a non-empty department. Without this guard the
        // repo call either cascades (silently orphaning employees / teams
        // / children) or bubbles a raw SQLSTATE[23000]/1451 FK error up
        // to the admin. Either outcome leaves the org tree inconsistent
        // and confuses the caller. Force the operator to reassign or
        // remove the dependents first.
        if (
            $department->employees()->count() > 0
            || $department->teams()->count() > 0
            || $department->positions()->count() > 0
            || $department->children()->count() > 0
        ) {
            throw ValidationException::withMessages([
                'id' => 'لا يمكن حذف القسم بينما يحتوي على موظفين أو فرق أو مسميات وظيفية أو أقسام فرعية.',
            ]);
        }

        return $this->departments->delete($department);
    }

    /**
     * Bulk-reassign every employee in one department to another —
     * typically run before a department is retired.
     */
    public function moveEmployees(Department $from, Department $to): int
    {
        return Employee::query()
            ->where('department_id', $from->id)
            ->update(['department_id' => $to->id]);
    }

    /**
     * Walks the ancestor chain of the proposed parent to make sure the
     * department being saved does not appear in it — that would make the
     * department an ancestor of its own parent, i.e. a cycle.
     */
    private function assertParentAssignmentIsNotCircular(?Department $department, int $parentId): void
    {
        if ($department !== null && $parentId === $department->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A department cannot be its own parent.',
            ]);
        }

        $visited = [];
        $currentId = $parentId;

        while ($currentId !== null) {
            if ($department !== null && $currentId === $department->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'This assignment would create a circular department hierarchy.',
                ]);
            }

            if (isset($visited[$currentId])) {
                // Defensive: an already-corrupt chain. Stop rather than loop forever.
                break;
            }

            $visited[$currentId] = true;
            $currentId = $this->departments->findParentId($currentId);
        }
    }
}
