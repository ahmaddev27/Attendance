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
    ->withoutOverlapping()
    // Once we scale beyond a single scheduler container the LLM warmup
    // would otherwise fan out N× per day and burn the Anthropic quota
    // for no additional benefit. onOneServer() elects a single runner
    // via the cache lock so only one node actually executes the batch.
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Retention / pruning schedules
|--------------------------------------------------------------------------
|
| Nothing prunes Sanctum tokens, failed jobs, notifications or the
| SMS/WhatsApp logs by default — on a busy tenant `notifications` alone
| runs into millions of rows in a matter of months. These daily jobs
| keep the hot tables bounded:
|
|   - sanctum:prune-expired : drop personal access tokens expired for
|     more than 60 days (matches Sanctum's own default expiration).
|   - queue:prune-failed    : drop failed_jobs rows older than 30 days;
|     support has already investigated whatever they wanted to by then.
|   - model:prune           : sweep any MassPrunable models feature waves
|     may add later — free hook, cheap when nothing is registered.
|   - taqat:prune-old-rows  : raw-DELETE sweeper for tables whose Models
|     live in other waves' ownership (see the command's docblock).
|
| Every job runs onOneServer() so scaling out the scheduler container
| doesn't multiply the work; withoutOverlapping() guards a slow run from
| stampeding the next day's tick.
*/
Schedule::command('sanctum:prune-expired --hours=1440')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping()
    ->name('sanctum:prune');

Schedule::command('queue:prune-failed --hours=720')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping()
    ->name('failed:prune');

Schedule::command('model:prune')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping()
    ->name('model:prune');

Schedule::command('taqat:prune-old-rows')
    ->dailyAt('03:00')
    ->timezone('Asia/Amman')
    ->onOneServer()
    ->withoutOverlapping()
    ->name('taqat:prune-old-rows');

/*
|--------------------------------------------------------------------------
| Auto-close forgotten attendance
|--------------------------------------------------------------------------
|
| Runs every 15 minutes. For every open attendance session (check_in_at
| set, check_out_at null), looks up the employee's WorkSchedule and, if
| the schedule's declared shift-end for that date has already passed,
| stamps check_out_at at the shift-end (not now()) — the employee was
| expected to leave then, so that's the honest closing time. Flexible
| schedules and employees without a schedule are skipped.
|
| 15-minute cadence catches "forgot to scan out on my way out the door"
| within the same day; anything older than 48h stays untouched (admin
| correction path).
*/
Schedule::command('taqat:auto-close-attendance')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->name('taqat:auto-close-attendance');

/*
|--------------------------------------------------------------------------
| Forgot-checkout reminder
|--------------------------------------------------------------------------
|
| Runs every 15 minutes alongside the auto-close sweep. For every open
| attendance session on a non-flexible schedule, checks whether the
| shift-end is within ~20-40 minutes from now and — if so — dispatches
| an OpenSessionReminderNotification (push + DB) to give the employee
| a chance to close the session themselves before auto-close stamps
| check_out_at at the schedule's declared shift-end.
|
| The command memoizes per attendance row (Cache::add for 6h) so no
| session is pinged twice inside the window. Ordering: this schedules
| AFTER the auto-close entry above so both share the same cadence but
| the reminder always references the currently-open population.
*/
Schedule::command('taqat:notify-open-sessions')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->name('taqat:notify-open-sessions');

/*
|--------------------------------------------------------------------------
| Recruitment SLA + stale-lead sweeps
|--------------------------------------------------------------------------
|
| Two schedules feeding the Recruitment inbox:
|
|   - recruitment:scan-sla         : hourly sweep that finds every open
|     JobRequirement whose current stage has exceeded its sla_hours and
|     notifies both the current stage owner and the case owner. Runs
|     in the background so a slow batch never blocks the scheduler tick.
|   - recruitment:scan-stale-leads : 09:00 daily reminder for every
|     active Lead not touched in the past 7 days (see the command for
|     the --days option).
|
| Both use withoutOverlapping() to guard against a slow previous run
| stampeding the next tick.
*/
Schedule::command('recruitment:scan-sla')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('recruitment:scan-stale-leads')
    ->dailyAt('09:00')
    ->withoutOverlapping();
