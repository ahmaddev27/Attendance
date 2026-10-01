<?php

declare(strict_types=1);

namespace App\Modules\Organization\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Minimal shape embedded in other resources where only id + name + logo
 * matter (nav bar, admin header, cross-module pickers). Kept separate
 * from CompanyResource so unrelated consumers don't ship timezone +
 * settings they'll never read.
 *
 * @mixin Company
 */
class CompanySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $this->logo_path
                ? Storage::disk('public')->url($this->logo_path)
                : null,
        ];
    }
}
