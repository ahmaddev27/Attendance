<?php

namespace App\Models;

use App\Enums\AttendanceType;
use App\Enums\FraudCheckStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'employee_id', 'type', 'scanned_at',
        'ip_address', 'latitude', 'longitude', 'fraud_check_status',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'type' => AttendanceType::class,
        'fraud_check_status' => FraudCheckStatus::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
