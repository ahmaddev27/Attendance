<?php

declare(strict_types=1);

namespace App\Modules\Requests\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Modules\Requests\Controllers\Concerns\ResolvesActingEmployee;
use App\Modules\Requests\Requests\ApprovalActionRequest;
use App\Modules\Requests\Resources\RequestResource;
use App\Modules\Requests\Services\ApprovalService;
use App\Modules\Requests\Services\RequestService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin-facing view over every request, plus the four decision actions.
 * "Admin" here just means auth:sanctum for now, same caveat every other
 * M1-M4 admin controller in this codebase carries — per-permission
 * authorization is layered on in a later milestone.
 */
class RequestController extends Controller
{
    use ResolvesActingEmployee;

    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly RequestService $requests,
        private readonly ApprovalService $approvals,
    ) {}

    public function index(HttpRequest $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'employee_id',
            'request_type_id',
            'status',
            'search',
            'from',
            'to',
        ]);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return RequestResource::collection($this->requests->listAdmin($filters, $perPage));
    }

    public function show(RequestModel $request): RequestResource
    {
        return new RequestResource($this->requests->find($request->id));
    }

    public function approve(ApprovalActionRequest $httpRequest, RequestModel $request): RequestResource
    {
        $approver = $this->resolveActingEmployee($httpRequest);

        return new RequestResource($this->approvals->approve($request, $approver, $httpRequest->validated('comment')));
    }

    public function reject(ApprovalActionRequest $httpRequest, RequestModel $request): RequestResource
    {
        $approver = $this->resolveActingEmployee($httpRequest);

        return new RequestResource($this->approvals->reject($request, $approver, (string) $httpRequest->validated('comment')));
    }

    public function return(ApprovalActionRequest $httpRequest, RequestModel $request): RequestResource
    {
        $approver = $this->resolveActingEmployee($httpRequest);

        return new RequestResource($this->approvals->return($request, $approver, (string) $httpRequest->validated('comment')));
    }

    public function forward(ApprovalActionRequest $httpRequest, RequestModel $request): RequestResource
    {
        $approver = $this->resolveActingEmployee($httpRequest);
        $forwardTo = Employee::query()->findOrFail($httpRequest->validated('forwarded_to_id'));

        return new RequestResource($this->approvals->forward($request, $approver, $forwardTo, (string) $httpRequest->validated('comment')));
    }
}
