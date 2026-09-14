<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Shared\Exceptions\ImmutableActivityLogException;

test('activity() writes through the insert-only model', function () {
    expect(activity('auth')->log('login'))->toBeInstanceOf(ActivityLog::class);
});

test('an activity row cannot be updated through Eloquent', function () {
    $entry = activity('auth')->withProperties(['ip' => '198.51.100.4'])->log('login');

    expect(fn () => $entry->update(['description' => 'tampered']))
        ->toThrow(ImmutableActivityLogException::class)
        ->and(fn () => ActivityLog::query()->findOrFail($entry->id)->forceFill(['properties' => ['ip' => '10.0.0.1']])->save())
        ->toThrow(ImmutableActivityLogException::class);

    $stored = ActivityLog::query()->findOrFail($entry->id);

    expect($stored->description)->toBe('login')
        ->and($stored->properties['ip'])->toBe('198.51.100.4');
});

test('an activity row cannot be deleted through Eloquent', function () {
    $entry = activity('auth')->log('login');

    expect(fn () => $entry->delete())
        ->toThrow(ImmutableActivityLogException::class)
        ->and(fn () => ActivityLog::destroy($entry->id))
        ->toThrow(ImmutableActivityLogException::class);

    $this->assertDatabaseHas('activity_log', ['id' => $entry->id]);
});

test('activitylog:clean still prunes rows past the retention window', function () {
    config(['activitylog.delete_records_older_than_days' => 365]);

    $expired = activity('auth')->createdAt(now()->subDays(400))->log('login');
    $retained = activity('auth')->createdAt(now()->subDays(30))->log('login');

    $this->artisan('activitylog:clean')
        ->expectsOutputToContain('Deleted 1 record(s)')
        ->assertSuccessful();

    $this->assertDatabaseMissing('activity_log', ['id' => $expired->id]);
    $this->assertDatabaseHas('activity_log', ['id' => $retained->id]);
});
