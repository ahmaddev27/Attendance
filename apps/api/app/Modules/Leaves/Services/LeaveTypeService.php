<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Services;

use App\Models\LeaveType;
use App\Modules\Leaves\Repositories\LeaveTypeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class LeaveTypeService
{
    public function __construct(
        private readonly LeaveTypeRepository $leaveTypes,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->leaveTypes->paginate($filters, $perPage);
    }

    public function find(int $id): LeaveType
    {
        return $this->leaveTypes->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LeaveType
    {
        return $this->leaveTypes->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LeaveType $leaveType, array $data): LeaveType
    {
        return $this->leaveTypes->update($leaveType, $data);
    }

    /**
     * Soft-deletes the type, unless it has ever been used — deleting a
     * type with existing balances/requests would orphan historical data
     * (or, for requests, hit the leave_requests.leave_type_id
     * restrictOnDelete constraint at the database level). Deactivating via
     * is_active is the correct way to retire a type that has been used.
     */
    public function delete(LeaveType $leaveType): void
    {
        if ($leaveType->requests()->exists() || $leaveType->balances()->exists()) {
            throw ValidationException::withMessages([
                'leave_type' => 'This leave type cannot be deleted because it already has balances or leave requests. Deactivate it instead.',
            ]);
        }

        $this->leaveTypes->delete($leaveType);
    }
}
