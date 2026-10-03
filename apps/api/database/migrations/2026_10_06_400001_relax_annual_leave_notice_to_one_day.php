<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner's call 2026-10-03: annual leave can be requested with as little
 * as one day's notice (was 7). Production never seeds, so the LeaveSeeder
 * change on its own would only affect fresh installs — this migration
 * is how the loosened policy actually reaches prod.
 *
 * Guard with `>= 2` so an admin who already dialled annual back to 0 or
 * 1 (perfectly valid) does not get silently reset by a redeploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_types')
            ->where('code', 'annual')
            ->where('min_notice_days', '>=', 2)
            ->update(['min_notice_days' => 1]);
    }

    public function down(): void
    {
        // Restore the previous 7-day policy only for installs that are
        // still on the new value — don't trample an admin who customised
        // it after this migration ran.
        DB::table('leave_types')
            ->where('code', 'annual')
            ->where('min_notice_days', 1)
            ->update(['min_notice_days' => 7]);
    }
};
