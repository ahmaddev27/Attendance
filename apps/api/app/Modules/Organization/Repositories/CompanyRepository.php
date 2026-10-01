<?php

declare(strict_types=1);

namespace App\Modules\Organization\Repositories;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CompanyRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Company>
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
     * @return \Illuminate\Database\Eloquent\Builder<Company>
     */
    private function query(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        // withCount powers the "X قسم" badge on the companies list card —
        // one COUNT(*) subselect per row, no N+1. Mirrors
        // DepartmentRepository::query()'s withCount('employees') pattern.
        $query = Company::query()->withCount('departments');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->orderBy('name');
    }

    public function findOrFail(int $id): Company
    {
        return Company::query()->withCount('departments')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Company
    {
        return Company::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Company $company, array $attributes): Company
    {
        $company->update($attributes);

        return $company->refresh();
    }

    public function delete(Company $company): bool
    {
        return (bool) $company->delete();
    }
}
