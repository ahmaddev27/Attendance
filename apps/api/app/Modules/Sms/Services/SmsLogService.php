<?php

declare(strict_types=1);

namespace App\Modules\Sms\Services;

use App\Models\SmsLog;
use App\Modules\Sms\Repositories\SmsLogRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recent SMS attempts for the settings page. Admins cannot read the server
 * log, so this is where a welcome SMS that never arrived gets explained.
 */
final class SmsLogService
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 100;

    public function __construct(
        private readonly SmsLogRepository $logs,
    ) {}

    /**
     * @return Collection<int, SmsLog>
     */
    public function recent(int $limit = self::DEFAULT_LIMIT): Collection
    {
        return $this->logs->latest($limit);
    }
}
