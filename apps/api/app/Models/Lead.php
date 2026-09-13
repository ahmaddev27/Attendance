<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A prospective client captured by the Sales team before formal
 * engagement. Kept through conversion — LeadConversionService flags
 * status = Converted and stamps converted_client_id rather than
 * deleting, so the acquisition history is queryable indefinitely.
 */
class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lead_number',
        'company_name',
        'company_website',
        'industry',
        'company_size',
        'country',
        'city',
        'contact_person',
        'contact_position',
        'contact_email',
        'contact_phone',
        'linkedin_url',
        'source',
        'status',
        'owner_id',
        'expected_hiring_volume',
        'notes',
        'last_contact_at',
        'next_followup_at',
        'converted_at',
        'converted_client_id',
        'lost_at',
        'lost_reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'expected_hiring_volume' => 'integer',
            'last_contact_at' => 'datetime',
            'next_followup_at' => 'datetime',
            'converted_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    /**
     * @return HasMany<LeadActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * States a Lead can still be actively worked in (as opposed to
     * terminal Converted/Lost). Used by dashboard funnels and the
     * "stale lead" scheduler to know which rows deserve attention.
     */
    public function isActive(): bool
    {
        return ! in_array($this->status, [LeadStatus::Converted, LeadStatus::Lost], true);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            LeadStatus::Converted->value,
            LeadStatus::Lost->value,
        ]);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('owner_id', $userId);
    }
}
