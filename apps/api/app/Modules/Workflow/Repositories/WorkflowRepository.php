<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Repositories;

use App\Models\Workflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class WorkflowRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['steps'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Workflow::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderBy('name')->paginate($perPage);
    }

    public function findOrFail(int $id): Workflow
    {
        return Workflow::query()->with(self::WITH)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Workflow
    {
        return Workflow::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Workflow $workflow, array $data): Workflow
    {
        $workflow->update($data);

        return $workflow->fresh(self::WITH);
    }

    public function delete(Workflow $workflow): void
    {
        $workflow->delete();
    }

    /**
     * @param  Builder<Workflow>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
    }
}
