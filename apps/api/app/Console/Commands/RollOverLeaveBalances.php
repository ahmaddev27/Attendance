<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Leaves\Services\LeaveBalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Opens a leave year: a balance row for every employee still on staff, with
 * unused days from the previous year carried over up to each leave type's
 * cap.
 *
 * Scheduled for 1 January 00:05 Asia/Amman. Running it again, or late with
 * --year, only recomputes the carried amount from the previous year's current
 * state, so a missed tick or a late approval of last year's leave is safe to
 * fix by re-running.
 */
class RollOverLeaveBalances extends Command
{
    protected $signature = 'leaves:annual-rollover
                            {--year= : The leave year to open (defaults to the current year in Asia/Amman)}';

    protected $description = 'Open the leave year and carry unused days over, capped per leave type.';

    public function __construct(private readonly LeaveBalanceService $balances)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = $this->option('year') !== null
            ? (int) $this->option('year')
            : (int) now('Asia/Amman')->year;

        if ($year < 2000 || $year > 2100) {
            $this->error('--year must be a four-digit year.');

            return self::INVALID;
        }

        $summary = $this->balances->rollOverYear($year);

        $message = sprintf(
            'leaves:annual-rollover %d — %d employee(s), %d balance row(s), %.2f day(s) carried over',
            $year,
            $summary['employees'],
            $summary['balances'],
            $summary['carried_days'],
        );

        Log::info($message);
        $this->info($message);

        return self::SUCCESS;
    }
}
