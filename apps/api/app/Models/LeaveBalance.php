<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LeaveBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    /** @use HasFactory<LeaveBalanceFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'entitlement',
        'used',
        'pending',
        'carry_over_from_previous',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitlement' => 'decimal:2',
            'used' => 'decimal:2',
            'pending' => 'decimal:2',
            'carry_over_from_previous' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * The raw ledger balance: what is left after both confirmed usage and
     * reservations held by pending requests are deducted from the
     * entitlement (plus any carried-over days).
     */
    public function getRemainingAttribute(): float
    {
        return ((float) $this->entitlement + (float) $this->carry_over_from_previous)
            - (float) $this->used
            - (float) $this->pending;
    }

    /**
     * How many additional days can still be reserved against this balance.
     *
     * Identical to `remaining` for a type that enforces its balance, except
     * when the leave type allows going negative — in that case there is no
     * ceiling, so this returns positive infinity rather than a number that
     * would falsely cap a new request. Callers comparing
     * `$balance->available >= $days` therefore never need to branch on
     * allow_negative_balance themselves; INF a>= anything is always true.
     *
     * API responses must translate INF to `null` before JSON-encoding it —
     * see LeaveBalanceResource — since json_encode() cannot represent it.
     */
    public function getAvailableAttribute(): float
    {
        if ($this->leaveType?->allow_negative_balance) {
            return INF;
        }

        return $this->remaining;
    }
}
