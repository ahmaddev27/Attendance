<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientSummaryResource extends JsonResource
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
            'country' => $this->country,
            'status' => $this->status?->value,
        ];
    }
}
