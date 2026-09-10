<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up to 2026_09_20_100005: the previous migration only zeroed
 * rows whose value equalled the old default of 300. Devices provisioned
 * with a custom window (60, 180, 600, …) still carry a non-zero value
 * and the kiosk keeps drawing the countdown.
 *
 * Since QR rotation was removed as a feature outright — form field is
 * gone, rotate button is gone, service class no longer scheduled — the
 * column value is meaningless. Zero every row unconditionally so no
 * consumer sees a stale rotation window.
 *
 * No down() reverses this: the intent is one-way (feature removed).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendance_devices')
            ->where('qr_rotates_every_seconds', '>', 0)
            ->update(['qr_rotates_every_seconds' => 0]);
    }

    public function down(): void
    {
        // Intentionally empty — restoring the old rotation windows on
        // rollback would re-surface the very UX regression the removal
        // was meant to fix.
    }
};
