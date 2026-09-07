<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leaves\Requests\AdjustBalanceRequest;
use App\Modules\Leaves\Resources\LeaveBalanceResource;
use App\Modules\Leaves\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveBalanceController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly LeaveBalanceService $balances,
    ) {}

    /**
     * GET /leave-balances?employee_id=X&year=Y
     *
     * With employee_id: that employee's balances (optionally scoped to a
     * year). Without it: a paginated, optionally year-filtered listing
     * across all employees, for an admin-wide overview.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $employeeId = $request->integer('employee_id') ?: null;
        $year = $request->integer('year') ?: null;

        if ($employeeId !== null) {
            return LeaveBalanceResource::collection($this->balances->findByEmployee($employeeId, $year));
        }

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return LeaveBalanceResource::collection($this->balances->paginate(['year' => $year], $perPage));
    }

    public function adjust(AdjustBalanceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $balance = $this->balances->adjust(
            employeeId: (int) $data['employee_id'],
            leaveTypeId: (int) $data['leave_type_id'],
            year: (int) $data['year'],
            delta: (float) $data['delta'],
            reason: (string) $data['reason'],
        );

        // Forced to 200 rather than relying on JsonResource's default
        // (which auto-switches to 201 whenever the underlying model's
        // wasRecentlyCreated is true) — this endpoint adjusts a balance,
        // and a balance row happening to be lazily created on first touch
        // is an implementation detail the client shouldn't see reflected
        // in the status code.
        return (new LeaveBalanceResource($balance->load('leaveType')))
            ->response()
            ->setStatusCode(200);
    }
}
