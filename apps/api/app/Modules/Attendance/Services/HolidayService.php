<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\Holiday;
use App\Modules\Attendance\Repositories\HolidayRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Thin CRUD wrapper — kept as a service (rather than calling the
 * repository from HolidayController directly) to stay consistent with the
 * project's Controllers -> Services -> Repositories layering.
 */
class HolidayService
{
    public function __construct(
        private readonly HolidayRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Holiday>
     */
    public function list(array $filters = []): Collection
    {
        return $this->repository->list($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Holiday
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Holiday $holiday, array $data): Holiday
    {
        return $this->repository->update($holiday, $data);
    }

    public function delete(Holiday $holiday): void
    {
        $this->repository->delete($holiday);
    }
}
