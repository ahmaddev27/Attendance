<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per WhatsApp send attempt.
 *
 * Written by SendWhatsappJob after the gateway returns (whether success
 * or failure). Serves two purposes:
 *   - operational: ops can eyeball recent failures without wading
 *     through the laravel.log stream, and can group by `status` to
 *     alert on error rates;
 *   - support: when a recipient claims they never received a message
 *     we can look up by phone + timestamp and hand Meta the
 *     provider_message_id (a WAMID) for a delivery trace.
 *
 * There is no relation to `users` or `employees` — a single phone can
 * change hands, and the log is a factual record of "we asked Meta to
 * send X to Y at time T", not a link into org data.
 */
class WhatsappLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'to',
        'body',
        'status',
        'provider_message_id',
        'error',
        'raw_response',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
        ];
    }
}
