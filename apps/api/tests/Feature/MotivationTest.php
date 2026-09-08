<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * Fresh employee + linked user + acting-as sanctum. Consolidates the
 * boilerplate all four tests need without dragging in the /me/leaves
 * ActsAsEmployeeUser trait (that one is scoped to a different module).
 */
function motivationActingAsEmployee(): User
{
    $employee = Employee::factory()->create();
    $user = User::factory()->create(['employee_id' => $employee->id]);

    Sanctum::actingAs($user);

    return $user;
}

function motivationCacheKey(User $user): string
{
    return sprintf('motivation:user:%d:%s', $user->id, now()->toDateString());
}

test('endpoint requires authentication', function () {
    $this->getJson('/api/me/motivation')->assertUnauthorized();
});

test('returns cached message when the cache is warm', function () {
    $user = motivationActingAsEmployee();

    // Guard against an accidental cache-miss falling through to the
    // real Anthropic API — a bare fake swallows every outbound call.
    Http::fake();

    $key = motivationCacheKey($user);
    $generatedAt = now()->subMinutes(30)->toIso8601String();
    Cache::put($key, [
        'message' => 'استمرّ في زخمك اليوم.',
        'generated_at' => $generatedAt,
    ], now()->addHours(24));

    $this->getJson('/api/me/motivation')
        ->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'استمرّ في زخمك اليوم.',
                'generated_at' => $generatedAt,
                'cached' => true,
            ],
        ]);

    Http::assertNothingSent();
});

test('falls back to a canned message when the claude client throws', function () {
    $user = motivationActingAsEmployee();
    Cache::forget(motivationCacheKey($user));

    // A 5xx after the client's retries surfaces as
    // MotivationUnavailableException — the service must swallow it
    // and hand back one of the canned lines instead. This test does
    // exercise the client's real retry backoff (~1.5s of sleep) so
    // that the fallback contract is verified end-to-end.
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    /** @var list<string> $fallbacks */
    $fallbacks = require resource_path('motivation-fallbacks.php');

    $response = $this->getJson('/api/me/motivation')
        ->assertOk()
        ->assertJsonStructure(['data' => ['message', 'generated_at', 'cached']])
        ->assertJson(['data' => ['cached' => false]]);

    expect($response->json('data.message'))->toBeIn($fallbacks);

    // Fallbacks are intentionally NOT cached — the next call should
    // re-attempt the upstream so a transient outage heals itself.
    expect(Cache::has(motivationCacheKey($user)))->toBeFalse();
});

test('caches a successful response for 24 hours under the expected key', function () {
    $user = motivationActingAsEmployee();
    $key = motivationCacheKey($user);
    Cache::forget($key);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-5',
            'content' => [
                ['type' => 'text', 'text' => 'رسالة تحفيزية اختبارية.'],
            ],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200),
    ]);

    $this->getJson('/api/me/motivation')
        ->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'رسالة تحفيزية اختبارية.',
                'cached' => false,
            ],
        ]);

    // Correct cache key format: motivation:user:{id}:{yyyy-mm-dd}.
    expect(Cache::has($key))->toBeTrue();

    $stored = Cache::get($key);
    expect($stored)->toBeArray()
        ->and($stored['message'])->toBe('رسالة تحفيزية اختبارية.')
        ->and($stored['generated_at'])->toBeString();

    // Second call same day must be served from cache — the fake
    // would otherwise fire again and we'd see cached=false. And we
    // assert exactly ONE upstream call regardless of dashboard hits.
    $this->getJson('/api/me/motivation')
        ->assertOk()
        ->assertJson(['data' => ['message' => 'رسالة تحفيزية اختبارية.', 'cached' => true]]);

    Http::assertSentCount(1);
});
