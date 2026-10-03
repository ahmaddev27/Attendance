<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\CandidateScreening;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateScreening
 */
class CandidateScreeningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'scorecard' => $this->scorecard,
            'overall_score' => $this->overall_score !== null ? (float) $this->overall_score : null,
            'passed' => (bool) $this->passed,
            'recommendation' => $this->recommendation,
            'notes' => $this->notes,
            'scored_at' => $this->scored_at?->toIso8601String(),
            'scored_by' => $this->whenLoaded('scoredBy', fn () => $this->scoredBy === null ? null : [
                'id' => $this->scoredBy->id,
                'name' => $this->scoredBy->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
