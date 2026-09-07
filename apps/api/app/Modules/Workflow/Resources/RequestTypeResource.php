<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Resources;

use App\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestType
 */
class RequestTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'workflow_id' => $this->workflow_id,
            'workflow' => $this->whenLoaded('workflow', fn () => $this->workflow === null ? null : [
                'id' => $this->workflow->id,
                'name' => $this->workflow->name,
            ]),
            'form_schema' => $this->form_schema,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
