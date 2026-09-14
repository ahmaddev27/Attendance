<?php

declare(strict_types=1);

/**
 * docs/v2 decision 9: the audit trail stays online for two years. Nothing
 * pruned activity_log before, so it grew without bound once the core HR
 * models started writing to it.
 */
test('the audit trail keeps two years of history online', function () {
    $withinTwoYears = activity('auth')->createdAt(now()->subDays(729))->log('login');
    $olderThanTwoYears = activity('auth')->createdAt(now()->subDays(731))->log('login');

    $this->artisan('activitylog:clean')->assertSuccessful();

    $this->assertDatabaseHas('activity_log', ['id' => $withinTwoYears->id]);
    $this->assertDatabaseMissing('activity_log', ['id' => $olderThanTwoYears->id]);
});

test('audit retention is scheduled every night without waiting for a confirmation', function () {
    // activitylog:clean asks "are you sure?" when APP_ENV is production, and
    // the scheduler cannot answer, so without --force it would never delete.
    $this->artisan('schedule:list')
        ->expectsOutputToContain('activitylog:clean --force')
        ->assertSuccessful();
});
