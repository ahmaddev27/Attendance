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
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Holiday::query()->orderBy('date');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Non-paginated listing — the admin holidays page reads `data.data`
     * as a flat array and filters client-side by year/type, so pagination
     * would silently truncate its list.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Holiday>
     */
    public function list(array $filters = []): Collection
    {
        $query = Holiday::query()->orderBy('date');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * @param  Builder<Holiday>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['year'])) {
            $query->whereYear('date', (int) $filters['year']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
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
                // Bare where() on the DATE column so the (date, name)
                // UNIQUE index is used for the range scan (DATE()
                // wrappers via whereDate() would disqualify it).
                $query->where(function (Builder $query) use ($start, $end) {
                    $query->where('date', '>=', $start->toDateString())
                        ->where('date', '<=', $end->toDateString());
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
