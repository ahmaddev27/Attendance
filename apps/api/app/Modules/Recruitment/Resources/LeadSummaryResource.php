<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact projection of a Lead for Kanban cards, cross-page chips, and
 * anywhere the full LeadResource is more than the UI actually needs.
 * Zero relations expanded on purpose so this can be attached to a large
 * collection with no N+1 risk.
 *
 * @mixin Lead
 */
class LeadSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_number' => $this->lead_number,
            'company_name' => $this->company_name,
            'country' => $this->country,
            'status' => $this->status?->value,
            'source' => $this->source,
            'owner_id' => $this->owner_id,
            'expected_hiring_volume' => $this->expected_hiring_volume,
            'next_followup_at' => $this->next_followup_at?->toIso8601String(),
        ];
    }
}
