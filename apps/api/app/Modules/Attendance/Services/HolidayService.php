<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\Holiday;
use App\Modules\Attendance\Repositories\HolidayRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
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
