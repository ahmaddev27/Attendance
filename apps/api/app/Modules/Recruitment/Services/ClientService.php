<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Client;
use App\Models\User;
use App\Modules\Recruitment\Events\ClientCreated;
use App\Modules\Recruitment\Repositories\ClientRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientService
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->clients->paginate($filters, $perPage);
    }

    public function find(int $id): Client
    {
        return $this->clients->findOrFail($id);
    }

    public function profile(int $id): Client
    {
        return $this->clients->findForProfile($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Client
    {
        $force = (bool) ($data['force'] ?? false);
        unset($data['force']);

        // Soft dedup on (company_name, country) mirrors LeadService.
        // The DB UNIQUE constraint is the last line — this branch is the
        // graceful path that offers the caller a chance to reuse.
        if (! $force && $this->clients->duplicateExists(
            (string) $data['company_name'],
            $data['country'] ?? null,
        )) {
            throw ValidationException::withMessages([
                'company_name' => 'يوجد عميل بنفس الاسم والدولة. مرّر force=true للحفظ بدون دمج.',
            ]);
        }

        $client = DB::transaction(function () use ($data, $actor) {
            $data['client_number'] = $this->numbers->nextClientNumber();
            $data['account_manager_id'] = $data['account_manager_id'] ?? $actor->id;

            return $this->clients->create($data);
        });

        ClientCreated::dispatch($client);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client
    {
        return $this->clients->update($client, $data);
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }
}
