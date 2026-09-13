<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_number' => $this->client_number,

            'company_name' => $this->company_name,
            'company_website' => $this->company_website,
            'industry' => $this->industry,
            'company_size' => $this->company_size,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,

            'tax_number' => $this->tax_number,
            'payment_terms' => $this->payment_terms,
            'payment_terms_notes' => $this->payment_terms_notes,

            'status' => $this->status?->value,

            'account_manager_id' => $this->account_manager_id,
            'account_manager' => $this->whenLoaded('accountManager', fn () => $this->accountManager === null ? null : [
                'id' => $this->accountManager->id,
                'name' => $this->accountManager->name,
                'email' => $this->accountManager->email,
            ]),

            'source_lead_id' => $this->source_lead_id,
            'source_lead' => $this->whenLoaded('sourceLead', fn () => $this->sourceLead === null ? null : [
                'id' => $this->sourceLead->id,
                'lead_number' => $this->sourceLead->lead_number,
                'company_name' => $this->sourceLead->company_name,
            ]),

            'primary_contact' => $this->whenLoaded('primaryContact', fn () => $this->primaryContact === null ? null : (new ClientContactResource($this->primaryContact))->toArray($request)),

            'notes' => $this->notes,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
