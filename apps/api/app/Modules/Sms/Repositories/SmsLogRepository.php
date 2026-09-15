<?php

declare(strict_types=1);

namespace App\Modules\Sms\Repositories;

use App\Models\SmsLog;
use Illuminate\Database\Eloquent\Collection;

final class SmsLogRepository
{
    /**
     * Newest attempts first. Ordered by id: rows are only ever inserted, so
     * it follows send time and uses the primary key.
     *
     * @return Collection<int, SmsLog>
     */
    public function latest(int $limit): Collection
    {
        return SmsLog::query()->latest('id')->limit($limit)->get();
    }
}
