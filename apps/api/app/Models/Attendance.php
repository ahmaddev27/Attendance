<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'check_in_at',
        'check_out_at',
        'check_in_ip',
        'check_in_lat',
        'check_in_lng',
        'check_in_device_id',
        'check_out_ip',
        'check_out_lat',
        'check_out_lng',
        'check_out_device_id',
        'total_minutes',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_lat' => 'decimal:7',
            'check_in_lng' => 'decimal:7',
            'check_out_lat' => 'decimal:7',
            'check_out_lng' => 'decimal:7',
            'total_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'status' => AttendanceStatus::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function checkInDevice(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'check_in_device_id');
    }

    public function checkOutDevice(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'check_out_device_id');
    }

    public function getTotalHoursAttribute(): float
    {
        return round(($this->total_minutes ?? 0) / 60, 2);
    }
}
