<?php

declare(strict_types=1);

namespace App\Modules\Requests\Repositories;

use App\Models\Employee;
use App\Models\Request as RequestModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RequestRepository
{
    /**
     * Relations eager-loaded on every read so the resource layer never
     * triggers an N+1 query per row.
     *
     * @var list<string>
     */
    private const WITH = ['employee', 'requestType', 'currentStep', 'latestApproval.approver', 'latestApproval.forwardedTo'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = RequestModel::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForEmployee(Employee $employee, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->paginate([...$filters, 'employee_id' => $employee->id], $perPage);
    }

    public function paginatePendingForApprover(Employee $employee, int $perPage): LengthAwarePaginator
    {
        return RequestModel::query()
            ->with(self::WITH)
            ->pendingForApprover($employee)
            ->orderBy('submitted_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): RequestModel
    {
        return RequestModel::query()->with(self::WITH)->findOrFail($id);
    }

    /**
     * Re-fetches the request with a row lock, for use inside a transaction
     * that is about to transition its status (approve/reject/return/
     * forward) — same lockForUpdate-inside-transaction pattern as
     * LeaveRequestRepository::findForUpdate().
     */
    public function findForUpdate(int $id): RequestModel
    {
        return RequestModel::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RequestModel
    {
        return RequestModel::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  Builder<RequestModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['employee_id', 'request_type_id', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
    }
}
