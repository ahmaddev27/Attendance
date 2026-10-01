<?php

declare(strict_types=1);

namespace App\Modules\Organization\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // `logo_path` is an internal storage key. The frontend never
            // uses it directly — it reads `logo_url`, which is derived
            // from the public disk here so every consumer gets the same
            // absolute URL without duplicating the storage lookup.
            'logo_url' => $this->logo_path
                ? Storage::disk('public')->url($this->logo_path)
                : null,
            'timezone' => $this->timezone,
            'settings' => $this->settings ?? [],
            // Populated by CompanyRepository::query()->withCount('departments').
            // Frontend renders it in the "X قسم" badge on each card.
            'departments_count' => $this->departments_count ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
