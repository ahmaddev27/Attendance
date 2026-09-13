<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\DB;

/**
 * Manages the "single primary contact per client" invariant that
 * MySQL/SQLite can't cleanly enforce with a partial unique index —
 * every setPrimary flip runs inside a transaction that first zeroes
 * out any other primary for that client.
 */
class ClientContactService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Client $client, array $data): ClientContact
    {
        return DB::transaction(function () use ($client, $data) {
            $wantsPrimary = (bool) ($data['is_primary'] ?? false);
            // First contact on a client is always primary — the
            // "primary" role isn't optional at zero.
            if ($client->contacts()->count() === 0) {
                $wantsPrimary = true;
            }

            if ($wantsPrimary) {
                $client->contacts()->where('is_primary', true)->update(['is_primary' => false]);
            }

            return $client->contacts()->create([
                'full_name' => $data['full_name'],
                'position' => $data['position'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'is_primary' => $wantsPrimary,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClientContact $contact, array $data): ClientContact
    {
        return DB::transaction(function () use ($contact, $data) {
            $incomingPrimary = array_key_exists('is_primary', $data)
                ? (bool) $data['is_primary']
                : null;

            // Promotion to primary — zero out any other primary first.
            if ($incomingPrimary === true && ! $contact->is_primary) {
                $contact->client
                    ->contacts()
                    ->where('id', '!=', $contact->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            // Demoting the only primary to false is refused — every
            // client should always have exactly one primary once at
            // least one contact exists. The caller must promote a
            // different contact first.
            if ($incomingPrimary === false && $contact->is_primary) {
                $siblings = $contact->client->contacts()->where('id', '!=', $contact->id)->exists();
                if (! $siblings) {
                    $data['is_primary'] = true;
                }
            }

            $contact->fill($data)->save();

            return $contact->fresh() ?? $contact;
        });
    }

    public function delete(ClientContact $contact): void
    {
        DB::transaction(function () use ($contact) {
            $wasPrimary = (bool) $contact->is_primary;
            $client = $contact->client;

            $contact->delete();

            // Promote the oldest remaining contact so the client still
            // has a primary (if any contacts remain at all).
            if ($wasPrimary && $client !== null) {
                $fallback = $client->contacts()->orderBy('id')->first();
                $fallback?->update(['is_primary' => true]);
            }
        });
    }
}
