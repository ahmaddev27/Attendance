<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blanket-disable enforce_geo + enforce_ip on every existing device.
 *
 * Motivation: the user reports scans throw "Location is required" on a
 * device they never intended to lock down. Between the wave-h migration
 * defaults, factory rows, and admins toggling enforce_geo mid-config,
 * some rows carry `enforce_geo=true` without matching lat/lng — leaving
 * scan flow dead until an admin reopens the device and fixes it. Reset
 * everyone to fail-open so no scan is silently blocked; admins can
 * re-enable per device from the settings form when they're ready.
 *
 * No down() — restoring the previous mixed state would resurrect the
 * very bug this migration fixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendance_devices')->update([
            'enforce_geo' => false,
            'enforce_ip' => false,
        ]);
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};
