<?php

declare(strict_types=1);

namespace App\Modules\Requests\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Requests\Controllers\Concerns\ResolvesActingEmployee;
use App\Modules\Requests\Resources\RequestResource;
use App\Modules\Requests\Services\RequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The authenticated employee's own pending-approval queue — see
 * Request::scopePendingForApprover() for how "pending for this employee"
 * is determined across every approver_type plus recent forwards.
 */
class ApprovalInboxController extends Controller
{
    use ResolvesActingEmployee;

    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly RequestService $requests,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = $user?->employee;
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        // An account without a linked employee (bootstrap super-admin,
        // an integration user) can still be a designated approver
        // through SpecificRole steps — after the direct-manager -> super
        // admin migration, every legacy step routes here. Serve their
        // inbox from a role-only query instead of the Employee-scoped
        // scope (which would return zero rows against a NULL employee).
        if (! $employee) {
            $roleNames = $user ? $user->getRoleNames()->all() : [];

            if (empty($roleNames)) {
                return RequestResource::collection(
                    new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage)
                );
            }

            $paginator = \App\Models\Request::query()
                ->with(['requestType', 'employee', 'currentStep'])
                ->where('status', \App\Shared\Enums\RequestStatus::Pending)
                ->whereHas('currentStep', function ($step) use ($roleNames) {
                    $step->where('approver_type', \App\Shared\Enums\ApproverType::SpecificRole)
                        ->whereIn('approver_ref', $roleNames);
                })
                ->orderByDesc('submitted_at')
                ->paginate($perPage);

            return RequestResource::collection($paginator);
        }

        return RequestResource::collection($this->requests->pendingForApprover($employee, $perPage));
    }
}
