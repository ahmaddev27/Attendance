<?php

declare(strict_types=1);

namespace App\Modules\Employees\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 *
 * Minimal Employee projection used by non-admin endpoints that only need
 * "who is this person" for task/mention pickers (see EmployeeController::
 * myTeam). Deliberately omits every PII field the full EmployeeResource
 * exposes (phone, email, birth_date, notes, joining_date, status,
 * employment_type, department/team/position/manager) so a regular
 * employee querying their own team roster can never enumerate the
 * directory's private contact information.
 */
class EmployeeSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'full_name' => $this->full_name,
            // `avatar_path` is an internal storage key; the FE only ever
            // renders the resolved public URL.
            'avatar_url' => $this->avatar_url,
        ];
    }
}
