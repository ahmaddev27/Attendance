<?php

declare(strict_types=1);

namespace App\Modules\Employees\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'employment_type' => $this->employment_type?->value,
            'status' => $this->status?->value,
            'gender' => $this->gender?->value,
            'joining_date' => $this->joining_date?->toDateString(),
            'birth_date' => $this->birth_date?->toDateString(),
            // `avatar_path` is an internal storage key. The frontend
            // only ever renders the resolved public URL, so expose that
            // instead of the raw column.
            'avatar_url' => $this->avatar_url,
            'notes' => $this->notes,
            'position' => $this->whenLoaded('position', fn () => $this->position === null ? null : [
                'id' => $this->position->id,
                'title' => $this->position->title,
            ]),
            'department' => $this->whenLoaded('department', fn () => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'team' => $this->whenLoaded('team', fn () => $this->team === null ? null : [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ]),
            'direct_manager' => $this->whenLoaded('directManager', fn () => $this->directManager === null ? null : [
                'id' => $this->directManager->id,
                'full_name' => $this->directManager->full_name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            // Only present on the create response — EmployeeService::create
            // sets `generated_password` in memory (never persisted) so the
            // admin can hand it to the user out-of-band if the SMS didn't
            // land (no phone / carrier down). $this->when() drops the key
            // entirely on every other response.
            'generated_password' => $this->when(
                isset($this->generated_password),
                fn () => $this->generated_password,
            ),
        ];
    }
}
