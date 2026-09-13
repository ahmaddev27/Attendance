<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\Lead;
use App\Models\RecruitmentCase;
use App\Shared\Enums\ClientStatus;
use App\Shared\Enums\JobRequirementStatus;
use App\Shared\Enums\LeadStatus;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Aggregate reporting surface for the Recruitment module. Every method
 * returns lightweight counts / bucket rollups only — no per-row PII —
 * so both `view-leads` and `view-jobs` grantees can hit it without
 * running afoul of `export-recruitment-data`.
 *
 * Queries are indexed lookups on `status` / `created_at` columns
 * already covered by module migrations, so no dedicated cache is
 * introduced here (the whole payload is small and short-lived on the
 * dashboard page).
 */
class RecruitmentDashboardController extends Controller
{
    public function kpis(): JsonResponse
    {
        $activeLeadStatuses = array_map(
            fn (LeadStatus $s) => $s->value,
            array_filter(
                LeadStatus::cases(),
                fn (LeadStatus $s) => ! in_array($s, [LeadStatus::Converted, LeadStatus::Lost], true),
            ),
        );

        $openJobStatuses = [
            JobRequirementStatus::Draft->value,
            JobRequirementStatus::Active->value,
            JobRequirementStatus::OnHold->value,
        ];

        $openCaseStatuses = [
            RecruitmentCaseStatus::Draft->value,
            RecruitmentCaseStatus::Active->value,
            RecruitmentCaseStatus::OnHold->value,
        ];

        $startOfMonth = Carbon::now()->startOfMonth();

        return response()->json([
            'data' => [
                'leads_active' => Lead::query()->whereIn('status', $activeLeadStatuses)->count(),
                'leads_converted' => Lead::query()->where('status', LeadStatus::Converted->value)->count(),
                'leads_lost' => Lead::query()->where('status', LeadStatus::Lost->value)->count(),
                'clients_active' => Client::query()->where('status', ClientStatus::Active->value)->count(),
                'cases_open' => RecruitmentCase::query()->whereIn('status', $openCaseStatuses)->count(),
                'jobs_open' => JobRequirement::query()->whereIn('status', $openJobStatuses)->count(),
                'jobs_filled_this_month' => JobRequirement::query()
                    ->where('status', JobRequirementStatus::Filled->value)
                    ->where('completed_at', '>=', $startOfMonth)
                    ->count(),
            ],
        ]);
    }

    /**
     * Funnel breakdown for the dashboard chart: leads grouped by status
     * (all statuses, even zeros, so the chart columns stay stable) and
     * open jobs grouped by their current pipeline stage id.
     */
    public function funnel(): JsonResponse
    {
        $leadCounts = Lead::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $leadsByStatus = [];
        foreach (LeadStatus::cases() as $status) {
            $leadsByStatus[$status->value] = (int) ($leadCounts[$status->value] ?? 0);
        }

        $jobsByStage = JobRequirement::query()
            ->whereNotNull('current_stage_id')
            ->selectRaw('current_stage_id, COUNT(*) as total')
            ->groupBy('current_stage_id')
            ->pluck('total', 'current_stage_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        return response()->json([
            'data' => [
                'leads_by_status' => $leadsByStatus,
                'jobs_by_stage' => $jobsByStage,
            ],
        ]);
    }

    /**
     * Top 5 sales reps by successful lead conversions this quarter.
     * Uses converted_at (stamped by LeadConversionService) so a lead
     * created a year ago and converted last week still counts against
     * the current quarter's number.
     */
    public function leaderboard(): JsonResponse
    {
        $startOfQuarter = Carbon::now()->firstOfQuarter();

        $rows = Lead::query()
            ->where('status', LeadStatus::Converted->value)
            ->whereNotNull('owner_id')
            ->where('converted_at', '>=', $startOfQuarter)
            ->selectRaw('owner_id, COUNT(*) as conversions')
            ->groupBy('owner_id')
            ->orderByDesc('conversions')
            ->limit(5)
            ->with('owner:id,name,email')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Lead $row) => [
                'owner_id' => (int) $row->owner_id,
                'owner_name' => $row->owner?->name,
                'owner_email' => $row->owner?->email,
                'conversions' => (int) $row->conversions,
            ])->values(),
            'meta' => [
                'quarter_started_at' => $startOfQuarter->toIso8601String(),
            ],
        ]);
    }
}
