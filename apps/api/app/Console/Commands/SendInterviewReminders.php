<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Repositories\InterviewRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fires interview reminders in two cadences:
 *
 *   --horizon=hourly  → interviews happening in the next 1h; cron
 *     wires this up every hour.
 *   --horizon=daily   → interviews happening TODAY; cron wires this up
 *     at 08:00.
 *
 * Phase 2 Week 3 scope: notifies the interview's creator (which is the
 * same user who gets the "fill feedback" task). Fan-out to the real
 * panel comes with Phase 3's panelist assignment table.
 */
class SendInterviewReminders extends Command
{
    protected $signature = 'recruitment:interview-reminders {--horizon=hourly : hourly|daily}';

    protected $description = 'Send upcoming-interview reminders to internal interviewers.';

    public function __construct(
        private readonly InterviewRepository $interviews,
        private readonly NotificationService $notifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $horizon = (string) $this->option('horizon');
        $now = Carbon::now();

        [$from, $until, $hoursUntil] = match ($horizon) {
            'daily' => [$now->copy()->startOfDay(), $now->copy()->startOfDay()->addDay(), 24],
            default => [$now->copy(), $now->copy()->addHour(), 1],
        };

        $interviews = $this->interviews->scheduledBetween($from, $until);

        $sent = 0;
        foreach ($interviews as $interview) {
            $recipient = $interview->createdBy;
            if (! $recipient instanceof User) {
                continue;
            }

            try {
                $this->notifier->interviewReminder($interview, $recipient, $hoursUntil);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('interview-reminders failed for interview', [
                    'interview_id' => $interview->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = sprintf(
            'recruitment:interview-reminders(%s) — scanned %d, sent %d',
            $horizon,
            $interviews->count(),
            $sent,
        );
        Log::info($summary);
        $this->info($summary);

        return self::SUCCESS;
    }
}
