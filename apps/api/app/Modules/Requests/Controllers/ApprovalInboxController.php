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
        $employee = $this->resolveActingEmployee($request);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return RequestResource::collection($this->requests->pendingForApprover($employee, $perPage));
    }
}
