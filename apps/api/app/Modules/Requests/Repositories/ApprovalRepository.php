<?php

declare(strict_types=1);

namespace App\Modules\Requests\Repositories;

use App\Models\RequestApproval;
use Illuminate\Database\Eloquent\Collection;

class ApprovalRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['approver', 'forwardedTo', 'workflowStep'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RequestApproval
    {
        return RequestApproval::query()->create($data)->load(self::WITH);
    }

    /**
     * Full decision history for a request, oldest first — the audit trail
     * behind Request::latestApproval (which only surfaces the most recent
     * one for the compact resource view).
     *
     * @return Collection<int, RequestApproval>
     */
    public function listForRequest(int $requestId): Collection
    {
        return RequestApproval::query()
            ->with(self::WITH)
            ->where('request_id', $requestId)
            ->orderBy('decided_at')
            ->get();
    }
}
