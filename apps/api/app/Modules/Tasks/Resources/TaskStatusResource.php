<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Resources;

use App\Models\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskStatus
 */
class TaskStatusResource extends JsonResource
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
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_done_state' => $this->is_done_state,
            'is_cancelled_state' => $this->is_cancelled_state,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
