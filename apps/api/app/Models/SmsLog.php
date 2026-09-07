<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\SmsStatus;
use Database\Factories\SmsLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An audit row for a single outgoing SMS sent through the MTC gateway.
 * Written by App\Modules\Notifications\Services\Sms\SmsService
 * regardless of whether the gateway call succeeded — a failed send is
 * still logged, with `error_code`/`provider_response` populated instead
 * of left null, so the admin SMS log screen can show why it failed.
 */
class SmsLog extends Model
{
    /** @use HasFactory<SmsLogFactory> */
    use HasFactory;

    protected $fillable = [
        'phone',
        'message',
        'status',
        'provider_response',
        'error_code',
        'sent_at',
        'notifiable_type',
        'notifiable_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * The record that triggered this SMS (a User, an Employee, ...), if
     * any. Deliberately not a real morphs() FK — see the migration's
     * docblock — so this relation may resolve to null even when the
     * notifiable_type/id columns are populated, if that source row has
     * since been deleted.
     *
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<SmsLog>  $query
     * @return Builder<SmsLog>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', SmsStatus::Failed);
    }
}
