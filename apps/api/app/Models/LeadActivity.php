<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single touch-point with a Lead — call/meeting/email/note plus
 * system-generated status_change and owner_change entries. Timeline
 * queries on the Lead detail always sort by occurred_at desc; the
 * matching composite index lives on the migration.
 *
 * `type` is a plain string, not an enum, so admins can introduce new
 * activity kinds without a code deploy (e.g. "whatsapp"). The frontend
 * gracefully falls back to a generic icon for unknown types.
 */
class LeadActivity extends Model
{
    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'subject',
        'body',
        'occurred_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
