<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Modules\Recruitment\Requests\StoreClientRequest;
use App\Modules\Recruitment\Requests\UpdateClientRequest;
use App\Modules\Recruitment\Resources\ClientProfileResource;
use App\Modules\Recruitment\Resources\ClientResource;
use App\Modules\Recruitment\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly ClientService $clients,
    ) {}

    public function index(HttpRequest $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'status',
            'account_manager_id',
            'country',
            'industry',
            'search',
        ]);

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return ClientResource::collection($this->clients->paginate($filters, $perPage));
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($this->clients->find($client->id));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $client = $this->clients->create($request->validated(), $user);

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        return new ClientResource($this->clients->update($client, $request->validated()));
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->clients->delete($client);

        return response()->json(null, 204);
    }

    /**
     * Extended profile view — includes contacts and active cases so the
     * Client detail page renders in one round-trip.
     */
    public function profile(Client $client): ClientProfileResource
    {
        return new ClientProfileResource($this->clients->profile($client->id));
    }
}
