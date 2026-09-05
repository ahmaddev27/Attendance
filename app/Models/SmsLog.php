<?php

namespace App\Models;

use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['phone', 'message', 'status', 'provider_response', 'error_code', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'status' => SmsStatus::class,
    ];
}
