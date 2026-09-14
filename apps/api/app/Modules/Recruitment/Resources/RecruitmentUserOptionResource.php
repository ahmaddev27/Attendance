<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of a recruitment owner picker. Email is left out on purpose: the
 * list is readable by every recruitment viewer and the picker only needs a
 * name and employee number to tell people apart.
 *
 * @mixin User
 */
class RecruitmentUserOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'employee_number' => $this->employee_number,
        ];
    }
}
