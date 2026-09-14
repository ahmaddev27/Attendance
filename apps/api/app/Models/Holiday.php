<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\HolidayType;
use Carbon\Carbon;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'date',
        'name',
        'type',
        'is_recurring',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
            'type' => HolidayType::class,
        ];
    }

    /**
     * Matches holidays that fall exactly on the given date, or annual
     * (is_recurring) holidays whose month/day repeats on it regardless of
     * the year the holiday row was originally created for.
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where(function (Builder $query) use ($date) {
            // date is a true DATE column — bare where() keeps the
            // (date, name) UNIQUE index usable for the one-off match.
            $query->where('date', $date->toDateString())
                ->orWhere(function (Builder $query) use ($date) {
                    $query->where('is_recurring', true)
                        ->whereMonth('date', $date->month)
                        ->whereDay('date', $date->day);
                });
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'name', 'type', 'is_recurring'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('organization');
    }
}
