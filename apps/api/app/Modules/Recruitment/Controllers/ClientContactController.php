<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use App\Modules\Recruitment\Requests\StoreClientContactRequest;
use App\Modules\Recruitment\Requests\UpdateClientContactRequest;
use App\Modules\Recruitment\Resources\ClientContactResource;
use App\Modules\Recruitment\Services\ClientContactService;
use Illuminate\Http\JsonResponse;

/**
 * Nested under /clients/{client}/contacts. The {contact} implicit
 * binding is verified against $client here — Laravel's default binding
 * accepts any contact id regardless of parent, so a leaked id from
 * another client would otherwise resolve successfully.
 */
class ClientContactController extends Controller
{
    public function __construct(
        private readonly ClientContactService $contacts,
    ) {}

    public function store(StoreClientContactRequest $request, Client $client): JsonResponse
    {
        $contact = $this->contacts->create($client, $request->validated());

        return (new ClientContactResource($contact))->response()->setStatusCode(201);
    }

    public function update(UpdateClientContactRequest $request, Client $client, ClientContact $contact): ClientContactResource
    {
        $this->guardOwnership($client, $contact);

        return new ClientContactResource($this->contacts->update($contact, $request->validated()));
    }

    public function destroy(Client $client, ClientContact $contact): JsonResponse
    {
        $this->guardOwnership($client, $contact);

        $this->contacts->delete($contact);

        return response()->json(null, 204);
    }

    private function guardOwnership(Client $client, ClientContact $contact): void
    {
        if ($contact->client_id !== $client->id) {
            abort(404);
        }
    }
}
