<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\RecruitmentCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecruitmentCase
 */
class RecruitmentCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_number' => $this->case_number,

            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => $this->client === null ? null : (new ClientSummaryResource($this->client))->toArray($request)),

            'source_lead_id' => $this->source_lead_id,
            'source_lead' => $this->whenLoaded('sourceLead', fn () => $this->sourceLead === null ? null : (new LeadSummaryResource($this->sourceLead))->toArray($request)),

            'title' => $this->title,
            'description' => $this->description,

            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),

            'priority' => $this->priority,
            'status' => $this->status?->value,

            'target_hires' => $this->target_hires,
            'started_at' => $this->started_at?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
