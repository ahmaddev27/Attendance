<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\Client;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The /clients/{id}/profile shape: a ClientResource fused with tabs the
 * profile page needs immediately (contacts + open case count). Kept as
 * its own class so the plain ClientResource on list endpoints stays
 * cheap.
 *
 * @mixin Client
 */
class ClientProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Reuse the plain ClientResource so the two payloads never
        // drift on their shared fields.
        $base = (new ClientResource($this->resource))->toArray($request);

        $base['contacts'] = $this->whenLoaded('contacts', fn () => ClientContactResource::collection($this->contacts));

        $base['cases_summary'] = $this->whenLoaded('cases', function () {
            $cases = $this->cases;

            $open = $cases->filter(fn ($case) => in_array($case->status?->value, [
                RecruitmentCaseStatus::Draft->value,
                RecruitmentCaseStatus::Active->value,
                RecruitmentCaseStatus::OnHold->value,
            ], true))->values();

            return [
                'total' => $cases->count(),
                'open_count' => $open->count(),
                'latest' => $cases->sortByDesc('created_at')->take(5)->values()->map(fn ($case) => [
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                    'title' => $case->title,
                    'status' => $case->status?->value,
                    'created_at' => $case->created_at?->toIso8601String(),
                ])->all(),
            ];
        });

        return $base;
    }
}
