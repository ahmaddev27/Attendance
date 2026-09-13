<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One named person we deal with at a Client company. The "single
 * primary contact per client" invariant is enforced by
 * ClientContactService::setPrimary (portable across MySQL/SQLite);
 * the DB carries an index on (client_id, is_primary) for fast lookup.
 */
class ClientContact extends Model
{
    protected $fillable = [
        'client_id',
        'full_name',
        'position',
        'email',
        'phone',
        'linkedin_url',
        'is_primary',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
