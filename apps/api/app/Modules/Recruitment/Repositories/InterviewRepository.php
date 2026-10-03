<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\Interview;

/**
 * Thin stub for Phase 2 Week 1 — only the number-generator helper is
 * needed by the end of this slice. Week 3 fleshes this out with
 * scheduling queries + feedback aggregations.
 */
class InterviewRepository
{
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
}
