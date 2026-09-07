<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RequestType;
use App\Modules\Workflow\Requests\StoreRequestTypeRequest;
use App\Modules\Workflow\Requests\UpdateRequestTypeRequest;
use App\Modules\Workflow\Resources\RequestTypeResource;
use App\Modules\Workflow\Services\RequestTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequestTypeController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly RequestTypeService $requestTypes,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['is_active', 'workflow_id', 'search']);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return RequestTypeResource::collection($this->requestTypes->paginate($filters, $perPage));
    }

    public function store(StoreRequestTypeRequest $request): JsonResponse
    {
        $requestType = $this->requestTypes->create($request->validated());

        return (new RequestTypeResource($requestType))->response()->setStatusCode(201);
    }

    public function show(RequestType $request_type): RequestTypeResource
    {
        return new RequestTypeResource($this->requestTypes->find($request_type->id));
    }

    public function update(UpdateRequestTypeRequest $request, RequestType $request_type): RequestTypeResource
    {
        return new RequestTypeResource($this->requestTypes->update($request_type, $request->validated()));
    }

    public function destroy(RequestType $request_type): JsonResponse
    {
        $this->requestTypes->delete($request_type);

        return response()->json(null, 204);
    }
}
