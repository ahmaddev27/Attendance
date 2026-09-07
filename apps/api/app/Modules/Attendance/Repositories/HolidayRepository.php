<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Models\Holiday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HolidayRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Holiday::query()->orderBy('date')->paginate($perPage);
    }

    public function find(int $id): ?Holiday
    {
        return Holiday::find($id);
    }

    public function create(array $data): Holiday
    {
        return Holiday::create($data);
    }

    public function update(Holiday $holiday, array $data): Holiday
    {
        $holiday->update($data);

        return $holiday->refresh();
    }

    public function delete(Holiday $holiday): void
    {
        $holiday->delete();
    }

    /**
     * All calendar dates (as 'Y-m-d' strings) within [$start, $end] that are
     * holidays — either an exact one-off date, or an is_recurring holiday
     * whose month/day repeats on a date in the range regardless of which
     * year it was originally recorded for.
     *
     * @return Collection<int, string>
     */
    public function datesInRange(Carbon $start, Carbon $end): Collection
    {
        return Holiday::query()
            ->where(function (Builder $query) use ($start, $end) {
                // whereDate() rather than whereBetween on the raw strings —
                // see AttendanceRepository::forEmployeeInRange() for why a
                // bare 'Y-m-d' upper bound would otherwise drop the range's
                // last day on a loosely typed connection like SQLite.
                $query->where(function (Builder $query) use ($start, $end) {
                    $query->whereDate('date', '>=', $start->toDateString())
                        ->whereDate('date', '<=', $end->toDateString());
                })->orWhere('is_recurring', true);
            })
            ->get()
            ->flatMap(function (Holiday $holiday) use ($start, $end) {
                if (! $holiday->is_recurring) {
                    return [$holiday->date->toDateString()];
                }

                $matches = [];

                foreach (range($start->year, $end->year) as $year) {
                    $candidate = Carbon::create($year, $holiday->date->month, $holiday->date->day);

                    if ($candidate && $candidate->between($start, $end)) {
                        $matches[] = $candidate->toDateString();
                    }
                }

                return $matches;
            })
            ->unique()
            ->values();
    }
}
