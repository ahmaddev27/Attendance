<?php

declare(strict_types=1);

namespace App\Modules\System\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Readiness probe for the whole stack. The deploy gate rolls a release
 * back when it reports `down`, and the scheduled uptime workflow alerts
 * on anything other than `ok`.
 *
 * Critical checks decide availability: when one fails the API cannot
 * serve requests correctly, so the endpoint answers 503. Background
 * checks (scheduler, queue worker) only degrade the status — the API
 * still answers, but notifications, SMS and the sweeps silently stop,
 * which is precisely the failure nobody notices without a probe.
 *
 * Failure details go to the log, never into the response: the endpoint
 * is public.
 */
final class HealthCheckService
{
    public const STATUS_OK = 'ok';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_DOWN = 'down';

    public const CHECK_OK = 'ok';
    public const CHECK_FAILED = 'failed';
    public const CHECK_STALE = 'stale';
    public const CHECK_MISSING = 'missing';

    private const SCHEDULER_HEARTBEAT_KEY = 'health:heartbeat:scheduler';
    private const QUEUE_HEARTBEAT_KEY = 'health:heartbeat:queue';

    /** The scheduler beats every minute; three missed ticks means it is gone. */
    private const SCHEDULER_STALE_AFTER_SECONDS = 180;

    /** Queue beats are dispatched every minute and need a worker to land. */
    private const QUEUE_STALE_AFTER_SECONDS = 300;

    private const HEARTBEAT_TTL_SECONDS = 3600;

    /**
     * @return array{status: string, checks: array<string, string>}
     */
    public function run(): array
    {
        $critical = [
            'database' => $this->probe('database', fn () => DB::connection()->select('select 1')),
            'cache' => $this->probe('cache', fn () => $this->roundTripCache()),
            'storage' => $this->probe('storage', fn () => $this->roundTripStorage()),
        ];

        if ($this->usesRedis()) {
            $critical['redis'] = $this->probe('redis', fn () => Redis::connection()->ping());
        }

        $background = [
            'scheduler' => $this->heartbeat(self::SCHEDULER_HEARTBEAT_KEY, self::SCHEDULER_STALE_AFTER_SECONDS),
            'queue' => $this->heartbeat(self::QUEUE_HEARTBEAT_KEY, self::QUEUE_STALE_AFTER_SECONDS),
        ];

        return [
            'status' => $this->overallStatus($critical, $background),
            'checks' => [...$critical, ...$background],
        ];
    }

    public function recordSchedulerHeartbeat(): void
    {
        Cache::put(self::SCHEDULER_HEARTBEAT_KEY, now()->getTimestamp(), self::HEARTBEAT_TTL_SECONDS);
    }

    public function recordQueueHeartbeat(): void
    {
        Cache::put(self::QUEUE_HEARTBEAT_KEY, now()->getTimestamp(), self::HEARTBEAT_TTL_SECONDS);
    }

    /**
     * @param  callable(): mixed  $check
     */
    private function probe(string $name, callable $check): string
    {
        try {
            $check();

            return self::CHECK_OK;
        } catch (Throwable $e) {
            Log::error('health check failed', ['check' => $name, 'error' => $e->getMessage()]);

            return self::CHECK_FAILED;
        }
    }

    private function roundTripCache(): void
    {
        $key = 'health:probe:'.Str::random(16);
        $token = Str::random(32);

        Cache::put($key, $token, 30);
        $readBack = Cache::get($key);
        Cache::forget($key);

        if ($readBack !== $token) {
            throw new RuntimeException('cache did not return the value it just stored');
        }
    }

    /**
     * The local disk is configured with `throw => false`, so a failed write
     * shows up as a false return rather than an exception.
     */
    private function roundTripStorage(): void
    {
        $disk = Storage::disk('local');
        $path = 'health/probe-'.Str::random(16);
        $token = Str::random(32);

        if (! $disk->put($path, $token)) {
            throw new RuntimeException('local disk rejected the probe write');
        }

        $readBack = $disk->get($path);
        $disk->delete($path);

        if ($readBack !== $token) {
            throw new RuntimeException('local disk did not return the probe contents');
        }
    }

    private function heartbeat(string $key, int $staleAfterSeconds): string
    {
        try {
            $beat = Cache::get($key);
        } catch (Throwable) {
            return self::CHECK_MISSING;
        }

        if (! is_int($beat)) {
            return self::CHECK_MISSING;
        }

        return now()->getTimestamp() - $beat > $staleAfterSeconds ? self::CHECK_STALE : self::CHECK_OK;
    }

    private function usesRedis(): bool
    {
        return in_array('redis', [
            config('cache.default'),
            config('queue.default'),
            config('session.driver'),
        ], true);
    }

    /**
     * @param  array<string, string>  $critical
     * @param  array<string, string>  $background
     */
    private function overallStatus(array $critical, array $background): string
    {
        if (in_array(self::CHECK_FAILED, $critical, true)) {
            return self::STATUS_DOWN;
        }

        foreach ($background as $state) {
            if ($state !== self::CHECK_OK) {
                return self::STATUS_DEGRADED;
            }
        }

        return self::STATUS_OK;
    }
}
