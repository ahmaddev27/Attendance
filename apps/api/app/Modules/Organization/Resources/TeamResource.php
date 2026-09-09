<?php

declare(strict_types=1);

namespace App\Modules\Organization\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'code' => $this->department->code,
            ]),
            'name' => $this->name,
            'leader' => $this->whenLoaded('leader', fn () => $this->leader === null ? null : [
                'id' => $this->leader->id,
                'full_name' => $this->leader->full_name,
            ]),
            'description' => $this->description,
            'is_active' => $this->is_active,
            // Populated by TeamRepository::query()->withCount('employees').
            // Frontend renders it in the "عدد الموظفين" column.
            'employees_count' => $this->employees_count ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
