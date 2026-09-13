<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A company we have engaged with formally — the anchor for
 * RecruitmentCases, ClientContacts, and (Phase 3) Contracts.
 *
 * source_lead_id preserves the acquisition trail when a Lead was
 * converted into this Client; nullOnDelete lets the Lead be purged
 * without collateral damage to the Client's history.
 */
class Client extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'client_number',
        'company_name',
        'company_website',
        'industry',
        'company_size',
        'country',
        'city',
        'address',
        'tax_number',
        'payment_terms',
        'payment_terms_notes',
        'status',
        'account_manager_id',
        'source_lead_id',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    /**
     * @return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /**
     * @return HasOne<ClientContact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    /**
     * @return HasMany<RecruitmentCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(RecruitmentCase::class);
    }

    /**
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ClientStatus::Active);
    }

    /**
     * See Lead::getActivitylogOptions — same trade-offs, focused on
     * business fields to keep the timeline signal-rich.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recruitment.client');
    }
}
