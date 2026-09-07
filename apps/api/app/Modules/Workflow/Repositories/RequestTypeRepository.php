<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Repositories;

use App\Models\RequestType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RequestTypeRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['workflow'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = RequestType::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);
    }

    public function findOrFail(int $id): RequestType
    {
        return RequestType::query()->with(self::WITH)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RequestType
    {
        return RequestType::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RequestType $requestType, array $data): RequestType
    {
        $requestType->update($data);

        return $requestType->fresh(self::WITH);
    }

    public function delete(RequestType $requestType): void
    {
        $requestType->delete();
    }

    /**
     * @param  Builder<RequestType>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['workflow_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
    }
}
