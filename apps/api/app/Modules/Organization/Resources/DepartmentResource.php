<?php

declare(strict_types=1);

namespace App\Modules\Organization\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager === null ? null : [
                'id' => $this->manager->id,
                'full_name' => $this->manager->full_name,
            ]),
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
