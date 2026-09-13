<?php

declare(strict_types=1);

namespace App\Modules\System\Jobs;

use App\Modules\System\Services\HealthCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Dispatched every minute by the scheduler. It only lands when a queue
 * worker is alive, so a fresh timestamp proves the whole dispatch →
 * Redis → worker path works, not just that the scheduler is running.
 */
final class RecordQueueHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function handle(HealthCheckService $health): void
    {
        $health->recordQueueHeartbeat();
    }
}
