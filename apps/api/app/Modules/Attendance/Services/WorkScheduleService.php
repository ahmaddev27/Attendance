<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\WorkSchedule;
use App\Modules\Attendance\Repositories\WorkScheduleRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WorkScheduleService
{
    public function __construct(
        private readonly WorkScheduleRepository $repository,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkSchedule
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WorkSchedule $schedule, array $data): WorkSchedule
    {
        return $this->repository->update($schedule, $data);
    }

    public function delete(WorkSchedule $schedule): void
    {
        $this->repository->delete($schedule);
    }
}
