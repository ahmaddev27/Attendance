<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WorkScheduleRepository
{
    /**
     * @return Collection<int, WorkSchedule>
     */
    public function all(): Collection
    {
        return WorkSchedule::query()->orderBy('name')->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return WorkSchedule::query()->orderBy('name')->paginate($perPage);
    }

    public function find(int $id): ?WorkSchedule
    {
        return WorkSchedule::find($id);
    }

    public function findDefault(): ?WorkSchedule
    {
        return WorkSchedule::query()->where('is_active', true)->orderBy('id')->first();
    }

    public function create(array $data): WorkSchedule
    {
        return WorkSchedule::create($data);
    }

    public function update(WorkSchedule $schedule, array $data): WorkSchedule
    {
        $schedule->update($data);

        return $schedule->refresh();
    }

    public function delete(WorkSchedule $schedule): void
    {
        $schedule->delete();
    }
}
