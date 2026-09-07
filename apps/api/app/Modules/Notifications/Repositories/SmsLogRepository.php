<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Models\SmsLog;
use App\Shared\Enums\SmsStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * All persistence for the `sms_logs` audit trail. Kept separate from
 * SmsService so that service stays focused on orchestration (call the
 * gateway, then make sure the attempt is recorded) without knowing
 * anything about how that record is queried back out for the admin log
 * screen.
 */
class SmsLogRepository
{
    public function log(
        string $phone,
        string $message,
        SmsStatus $status,
        ?string $providerResponse,
        ?string $errorCode,
        ?Model $notifiable = null,
    ): SmsLog {
        return SmsLog::query()->create([
            'phone' => $phone,
            'message' => $message,
            'status' => $status,
            'provider_response' => $providerResponse,
            'error_code' => $errorCode,
            'sent_at' => now(),
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id' => $notifiable?->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters  phone, status, from, to
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return SmsLog::query()
            ->when($filters['phone'] ?? null, fn ($query, $phone) => $query->where('phone', 'like', "%{$phone}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('sent_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('sent_at', '<=', $to))
            ->latest('sent_at')
            ->paginate($perPage);
    }
}
