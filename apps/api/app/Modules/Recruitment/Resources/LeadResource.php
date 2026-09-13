<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full Lead payload for detail pages. Every relation is exposed via
 * whenLoaded so a caller reading through Repository::WITH gets the
 * expanded shape, while a code path that only fetched the bare model
 * doesn't accidentally trigger an N+1 lookup by touching it here.
 *
 * @mixin Lead
 */
class LeadResource extends JsonResource
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
            'company_website' => $this->company_website,
            'industry' => $this->industry,
            'company_size' => $this->company_size,
            'country' => $this->country,
            'city' => $this->city,

            'contact_person' => $this->contact_person,
            'contact_position' => $this->contact_position,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'linkedin_url' => $this->linkedin_url,

            'source' => $this->source,
            'status' => $this->status?->value,

            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),

            'expected_hiring_volume' => $this->expected_hiring_volume,
            'notes' => $this->notes,

            'last_contact_at' => $this->last_contact_at?->toIso8601String(),
            'next_followup_at' => $this->next_followup_at?->toIso8601String(),

            'converted_at' => $this->converted_at?->toIso8601String(),
            'converted_client_id' => $this->converted_client_id,
            'converted_client' => $this->whenLoaded('convertedClient', fn () => $this->convertedClient === null ? null : [
                'id' => $this->convertedClient->id,
                'client_number' => $this->convertedClient->client_number,
                'company_name' => $this->convertedClient->company_name,
            ]),

            'lost_at' => $this->lost_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
