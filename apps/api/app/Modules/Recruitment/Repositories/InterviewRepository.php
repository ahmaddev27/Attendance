<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\Interview;
use App\Shared\Enums\InterviewStatus;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reads + writes for Interview. Every list query eager-loads the chain
 * the UI needs (application → candidate + job) so the controller never
 * touches an N+1. Lock-for-update is reserved for the scheduling/cancel
 * paths where two concurrent writes would otherwise race on `status`.
 */
class InterviewRepository
{
    /**
     * @var list<string>
     */
    private const WITH = [
        'application.candidate',
        'application.jobRequirement',
        'feedbacks.interviewer',
        'createdBy',
    ];

    public function highestSequenceForYear(int $year): int
    {
        $prefix = "INT-{$year}-";

        $latest = Interview::withTrashed()
            ->where('interview_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('interview_number');

        if ($latest === null) {
            return 0;
        }

        return (int) mb_substr((string) $latest, mb_strlen($prefix));
    }

    public function findOrFail(int $id): Interview
    {
        return Interview::query()->with(self::WITH)->findOrFail($id);
    }

    public function findForUpdate(int $id): Interview
    {
        return Interview::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Interview
    {
        return Interview::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Interview $interview, array $data): Interview
    {
        $interview->fill($data)->save();

        return $interview->fresh(self::WITH) ?? $interview;
    }

    /**
     * Upcoming scheduled interviews ordered by scheduled_at. Used by the
     * calendar list in `/api/interviews` — defaults to open interviews
     * only so cancelled/completed rows don't crowd the view.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Interview::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_at')->paginate($perPage);
    }

    /**
     * Interviews whose scheduled_at falls in the half-open window
     * `[from, until)`. Used by the reminder command to pick "next hour"
     * or "today" without the SQLite date-cast pitfall mentioned in the
     * project's standing rules.
     *
     * @return Collection<int, Interview>
     */
    public function scheduledBetween(CarbonInterface $from, CarbonInterface $until): Collection
    {
        return Interview::query()
            ->with(self::WITH)
            ->where('status', InterviewStatus::Scheduled->value)
            ->where('scheduled_at', '>=', $from)
            ->where('scheduled_at', '<', $until)
            ->orderBy('scheduled_at')
            ->get();
    }

    public function countFeedbacks(Interview $interview): int
    {
        return $interview->feedbacks()->count();
    }

    /**
     * @param  Builder<Interview>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['application_id'])) {
            $query->where('application_id', (int) $filters['application_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        if (empty($filters['include_closed'])) {
            // Default to open interviews — the calendar's job is to show
            // what still needs to happen, not a wall of history.
            $query->where('status', InterviewStatus::Scheduled->value);
        }

        if (! empty($filters['from'])) {
            $query->where('scheduled_at', '>=', $filters['from']);
        }

        if (! empty($filters['until'])) {
            // Half-open: `until` is exclusive so a day-aligned "until
            // 2026-11-02" catches everything on 11-01 without pulling in
            // 11-02 00:00:00.
            $query->where('scheduled_at', '<', $filters['until']);
        }
    }
}
