<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\LeaveStatus;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'days',
        'reason',
        'attachment_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'workflow_instance_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:2',
            'status' => LeaveStatus::class,
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Pending);
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Approved);
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Rejected);
    }

    /**
     * Requests for the given employee, still holding a claim on the
     * balance (pending or approved), whose [start_date, end_date] span
     * intersects the given range. Used to block a new submission that
     * would double-book the same days.
     *
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeOverlapping(Builder $query, int $employeeId, \DateTimeInterface|string $start, \DateTimeInterface|string $end): Builder
    {
        return $query->where('employee_id', $employeeId)
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);
    }

    /**
     * Whether the owning employee is still allowed to change this
     * request's own fields (as opposed to only cancelling it).
     */
    public function isEditableByEmployee(): bool
    {
        return in_array($this->status, [LeaveStatus::Draft, LeaveStatus::Pending], true);
    }

    public function canBeApproved(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }

    public function canBeRejected(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [LeaveStatus::Draft, LeaveStatus::Pending, LeaveStatus::Approved], true);
    }
}
