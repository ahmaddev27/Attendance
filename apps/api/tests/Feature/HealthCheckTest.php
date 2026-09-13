<?php

use App\Modules\System\Jobs\RecordQueueHeartbeat;
use App\Modules\System\Services\HealthCheckService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function recordFreshHeartbeats(): void
{
    $health = app(HealthCheckService::class);
    $health->recordSchedulerHeartbeat();
    (new RecordQueueHeartbeat())->handle($health);
}

test('a healthy stack with live scheduler and queue reports ok', function () {
    recordFreshHeartbeats();

    $this->getJson('/api/health')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', 'ok')
        ->assertJsonPath('checks.cache', 'ok')
        ->assertJsonPath('checks.storage', 'ok')
        ->assertJsonPath('checks.scheduler', 'ok')
        ->assertJsonPath('checks.queue', 'ok')
        ->assertJsonStructure(['status', 'checks', 'time']);
});

test('missing heartbeats degrade the status but keep answering 200', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.scheduler', 'missing')
        ->assertJsonPath('checks.queue', 'missing');
});

test('a heartbeat older than its window is reported stale', function () {
    Carbon::setTestNow(now()->subMinutes(10));
    recordFreshHeartbeats();
    Carbon::setTestNow();

    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.scheduler', 'stale')
        ->assertJsonPath('checks.queue', 'stale');
});

test('an unreachable database takes the endpoint down with 503 and no error details', function () {
    recordFreshHeartbeats();
    $originalConnection = config('database.default');

    config(['database.connections.health_broken' => [
        'driver' => 'sqlite',
        'database' => base_path('composer.json').DIRECTORY_SEPARATOR.'missing.sqlite',
        'prefix' => '',
    ]]);
    config(['database.default' => 'health_broken']);

    try {
        $response = $this->getJson('/api/health');
    } finally {
        config(['database.default' => $originalConnection]);
        DB::purge('health_broken');
    }

    $response->assertStatus(503)
        ->assertJsonPath('status', 'down')
        ->assertJsonPath('checks.database', 'failed')
        ->assertJsonMissingPath('error')
        ->assertJsonMissingPath('message');
});

test('an unwritable storage disk takes the endpoint down', function () {
    recordFreshHeartbeats();
    $originalRoot = config('filesystems.disks.local.root');

    config(['filesystems.disks.local.root' => base_path('composer.json').DIRECTORY_SEPARATOR.'nope']);
    Storage::forgetDisk('local');

    try {
        $response = $this->getJson('/api/health');
    } finally {
        config(['filesystems.disks.local.root' => $originalRoot]);
        Storage::forgetDisk('local');
    }

    $response->assertStatus(503)
        ->assertJsonPath('status', 'down')
        ->assertJsonPath('checks.storage', 'failed');
});

test('both heartbeats are scheduled every minute', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('health:scheduler-heartbeat')
        ->expectsOutputToContain('health:queue-heartbeat')
        ->assertExitCode(0);
});
