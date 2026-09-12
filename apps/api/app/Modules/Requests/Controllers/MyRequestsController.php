<?php

declare(strict_types=1);

namespace App\Modules\Requests\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Request as RequestModel;
use App\Modules\Requests\Controllers\Concerns\ResolvesActingEmployee;
use App\Modules\Requests\Requests\SubmitRequestRequest;
use App\Modules\Requests\Resources\RequestResource;
use App\Modules\Requests\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Self-service endpoints under /me/requests. The target employee is
 * always request()->user()->employee — never a client-supplied id — so an
 * employee can only ever see or act on their own requests. Mirrors
 * App\Modules\Leaves\Controllers\EmployeeLeavesController.
 */
class MyRequestsController extends Controller
{
    use ResolvesActingEmployee;

    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly RequestService $requests,
    ) {}

    public function index(HttpRequest $httpRequest): AnonymousResourceCollection
    {
        $employee = $this->resolveActingEmployee($httpRequest);
        $filters = $httpRequest->only(['request_type_id', 'status']);
        $perPage = (int) $httpRequest->integer('per_page', self::DEFAULT_PER_PAGE);

        return RequestResource::collection($this->requests->listForEmployee($employee, $filters, $perPage));
    }

    public function show(HttpRequest $httpRequest, RequestModel $request): RequestResource
    {
        $employee = $this->resolveActingEmployee($httpRequest);

        if ($request->employee_id !== $employee->id) {
            abort(403, 'You may not view another employee\'s request.');
        }

        return new RequestResource($this->requests->find($request->id));
    }

    public function store(SubmitRequestRequest $request): JsonResponse
    {
        $employee = $this->resolveActingEmployee($request);

        $created = $this->requests->submit($employee, $request->validated());

        return (new RequestResource($created))->response()->setStatusCode(201);
    }

    public function cancel(HttpRequest $httpRequest, RequestModel $request): RequestResource
    {
        $employee = $this->resolveActingEmployee($httpRequest);

        if ($request->employee_id !== $employee->id) {
            abort(403, 'You may not cancel another employee\'s request.');
        }

        return new RequestResource($this->requests->cancel($request));
    }

    /**
     * Resubmit a returned request. Ownership is enforced HERE (via the
     * acting employee) rather than in the service so the service can
     * stay reusable from any future admin surface; the service still
     * checks that the request is in the `Returned` state.
     */
    public function resubmit(SubmitRequestRequest $httpRequest, RequestModel $request): RequestResource
    {
        $employee = $this->resolveActingEmployee($httpRequest);

        if ($request->employee_id !== $employee->id) {
            abort(403, 'You may not resubmit another employee\'s request.');
        }

        /** @var array<string, mixed> $formData */
        $formData = $httpRequest->validated()['form_data'] ?? [];

        return new RequestResource($this->requests->resubmit($request, $formData));
    }
}
