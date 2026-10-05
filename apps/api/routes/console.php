<?php

use App\Models\Employee;
use App\Modules\AI\Services\MotivationService;
use App\Modules\System\Jobs\RecordQueueHeartbeat;
use App\Modules\System\Services\HealthCheckService;
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
| Health heartbeats
|--------------------------------------------------------------------------
|
| /api/health cannot see background containers directly, so both leave a
| timestamp in the shared cache every minute. The scheduler beat proves
| `schedule:work` is ticking; the queued beat only lands when a worker
| picks it up, which also proves the queue path end to end.
*/
Schedule::call(fn () => app(HealthCheckService::class)->recordSchedulerHeartbeat())
    ->everyMinute()
    ->name('health:scheduler-heartbeat');

Schedule::job(new RecordQueueHeartbeat())
    ->everyMinute()
    ->name('health:queue-heartbeat');

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
        ->staffOnly()
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
|   - activitylog:clean     : drop audit rows past the two-year retention
|     (docs/v2 decision 9). --force because the command otherwise asks for
|     confirmation in production, which the scheduler cannot give.
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

Schedule::command('activitylog:clean --force')
    ->dailyAt('03:30')
    ->timezone('Asia/Amman')
    ->onOneServer()
    ->withoutOverlapping()
    ->name('activitylog:clean');

/*
|--------------------------------------------------------------------------
| Auto-close forgotten attendance
|--------------------------------------------------------------------------
|
| Runs ONCE per day at 00:10 Asia/Gaza — ten minutes after the Gaza
| day boundary. Deliberately NOT every 15 minutes through the shift:
| employees who legitimately stay past their declared shift-end
| (overtime) must have the entire Gaza day to scan out themselves. A
| mid-day sweep would stamp check_out_at at the schedule's shift-end
| while they are still at their desk, dropping the extra hours on the
| floor.
|
| At 00:10 Gaza any row still open from yesterday (or older) is the
| honest "forgot to scan out" case: the command stamps check_out_at
| at the schedule's declared shift-end (not now()) and re-runs the
| hours engine. attendance:recompute-hours runs 20 minutes later at
| 00:30 Gaza so the previous day's hours reflect the auto-closed rows.
*/
Schedule::command('taqat:auto-close-attendance')
    ->dailyAt('00:10')
    ->timezone('Asia/Gaza')
    ->onOneServer()
    ->withoutOverlapping()
    ->name('taqat:auto-close-attendance');

/*
|--------------------------------------------------------------------------
| Forgot-checkout reminder
|--------------------------------------------------------------------------
|
| Runs every 5 minutes alongside the auto-close sweep. For every open
| attendance session on a non-flexible schedule, checks whether the
| shift-end is within ~3-8 minutes from now and — if so — dispatches
| an OpenSessionReminderNotification (push + DB) to give the employee
| a chance to close the session themselves before auto-close stamps
| check_out_at at the schedule's declared shift-end.
|
| 5-min cadence matches the "5 minutes before end" promise; a longer
| cadence would leave the ping arriving late or not at all for a
| session whose shift-end lands between two ticks.
|
| The command memoizes per attendance row (Cache::add for 6h) so no
| session is pinged twice inside the window.
*/
Schedule::command('taqat:notify-open-sessions')
    ->everyFiveMinutes()
    ->timezone('Asia/Gaza')
    ->onOneServer()
    ->withoutOverlapping()
    ->name('taqat:notify-open-sessions');

/*
|--------------------------------------------------------------------------
| Nightly working-hours recompute
|--------------------------------------------------------------------------
|
| 00:30 Asia/Gaza — twenty minutes after taqat:auto-close-attendance
| has stamped yesterday's forgotten rows, so this recompute walks a
| settled picture. Explicit Gaza timezone (not config('app.timezone'))
| because attendance always belongs to Gaza regardless of what the
| container clock is set to; a UTC/Amman container would otherwise
| recompute the wrong day. Walks the previous day's attendance for
| every active employee and re-runs WorkingHoursCalculator against the
| current schedule so late / early / overtime minutes reflect any
| retroactive change (schedule edit, holiday backfilled late, admin
| corrects a timestamp). Idempotent: a clean day is a no-op.
*/
Schedule::command('attendance:recompute-hours')
    ->dailyAt('00:30')
    ->timezone('Asia/Gaza')
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground()
    ->name('attendance:recompute-hours');

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
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('recruitment:scan-stale-leads')
    ->dailyAt('09:00')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Phase 2 — Interview reminders + application SLA scan
|--------------------------------------------------------------------------
|
| Three new background sweeps the Phase 2 Week 3 slice depends on:
|
|   - recruitment:interview-reminders --horizon=hourly : every hour,
|     surfaces interviews happening within the next 1h.
|   - recruitment:interview-reminders --horizon=daily  : at 08:00,
|     surfaces today's interviews so the panel sees the day's list on
|     their morning inbox check.
|   - recruitment:application-sla-scan : hourly mirror of scan-sla but
|     for the per-application stage pointer added in Phase 2 (D6).
|
| onOneServer() guards against double-firing when we scale beyond one
| scheduler container; withoutOverlapping() keeps a slow run from
| stampeding the next tick.
*/
Schedule::command('recruitment:interview-reminders', ['--horizon=hourly'])
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('recruitment:interview-reminders', ['--horizon=daily'])
    ->dailyAt('08:00')
    ->timezone('Asia/Gaza')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('recruitment:application-sla-scan')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| Annual leave rollover
|--------------------------------------------------------------------------
|
| Opens the new leave year five minutes into 1 January (Amman time):
| a balance row per employee still on staff, with unused days carried
| over up to each leave type's cap. Re-running it later only recomputes
| the carried amount, so a missed tick is fixed with
| `php artisan leaves:annual-rollover --year=YYYY`.
*/
Schedule::command('leaves:annual-rollover')
    ->yearlyOn(1, 1, '00:05')
    ->timezone('Asia/Amman')
    ->onOneServer()
    ->withoutOverlapping()
    ->name('leaves:annual-rollover');
