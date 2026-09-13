<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One hiring engagement / campaign for a single Client. Groups the
 * job requirements that were opened together (e.g. "Q1 2026 Remote
 * Push") so a whole campaign can be closed, tracked and measured as
 * a unit — the layer of granularity between Client and JobRequirement
 * where deadlines and KPIs actually live.
 */
class RecruitmentCase extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'case_number',
        'client_id',
        'source_lead_id',
        'title',
        'description',
        'owner_id',
        'priority',
        'status',
        'target_hires',
        'started_at',
        'deadline',
        'completed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'priority' => 'normal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecruitmentCaseStatus::class,
            'target_hires' => 'integer',
            'started_at' => 'date',
            'deadline' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<JobRequirement, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(JobRequirement::class);
    }

    /**
     * @param  Builder<RecruitmentCase>  $query
     * @return Builder<RecruitmentCase>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RecruitmentCaseStatus::Draft->value,
            RecruitmentCaseStatus::Active->value,
            RecruitmentCaseStatus::OnHold->value,
        ]);
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
            ->useLogName('recruitment.case');
    }
}
