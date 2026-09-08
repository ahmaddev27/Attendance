<?php

use App\Models\Employee;
use App\Modules\AI\Services\MotivationService;
use App\Shared\Enums\EmployeeStatus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| AI Motivation cache warmup
|--------------------------------------------------------------------------
|
| Pre-generates today's motivational line for every active employee
| whose login account exists, so the first dashboard hit at the start
| of the working day is served straight from redis instead of paying
| the LLM round-trip. Runs at 07:00 Amman time — before staff arrive.
|
| Failures per employee are logged and swallowed so a single bad row
| doesn't halt the batch. Anthropic asks for polite pacing on burst
| traffic, so we sleep a second every 5 users to stay under ~5 rps.
*/
Schedule::call(function (): void {
    /** @var MotivationService $service */
    $service = app(MotivationService::class);

    $processed = 0;
    Employee::query()
        ->where('status', EmployeeStatus::Active->value)
        ->whereNotNull('user_id')
        ->with('user')
        ->chunkById(100, function ($employees) use ($service, &$processed): void {
            foreach ($employees as $employee) {
                $user = $employee->user;
                if ($user === null) {
                    continue;
                }

                try {
                    $service->for($user);
                } catch (\Throwable $e) {
                    Log::warning('Motivation warmup failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $processed++;
                if ($processed % 5 === 0) {
                    usleep(1_000_000); // 5 requests per second cap
                }
            }
        });
})
    ->dailyAt('07:00')
    ->timezone('Asia/Amman')
    ->name('ai:motivation:warmup')
    ->withoutOverlapping();
