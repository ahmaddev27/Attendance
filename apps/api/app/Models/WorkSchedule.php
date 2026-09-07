<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    /** @use HasFactory<WorkScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'timezone',
        'check_in_time',
        'check_out_time',
        'min_hours_per_day',
        'grace_late_minutes',
        'grace_early_leave_minutes',
        'workdays',
        'is_flexible',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'workdays' => 'array',
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
            'min_hours_per_day' => 'decimal:2',
            'grace_late_minutes' => 'integer',
            'grace_early_leave_minutes' => 'integer',
            'is_flexible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Whether the given date falls on one of this schedule's workdays.
     * `workdays` follows Laravel/Carbon convention: 0 = Sunday .. 6 = Saturday.
     */
    public function isWorkday(Carbon $date): bool
    {
        return in_array($date->dayOfWeek, $this->workdays ?? [], true);
    }

    /**
     * The minimum number of minutes an employee is expected to work per day.
     */
    public function expectedMinutes(): int
    {
        return (int) round(((float) $this->min_hours_per_day) * 60);
    }
}
