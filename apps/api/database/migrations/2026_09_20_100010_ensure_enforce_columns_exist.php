<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-healing migration for the enforce_geo / enforce_ip columns on
 * attendance_devices. Prod is showing:
 *
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'enforce_geo'
 *
 * on the update path — the earlier 2026_09_20_100002 migration is
 * either marked as run without having applied (partial-state migration
 * table) or was silently skipped. Either way, we can't rely on the
 * earlier migration alone; this one adds the columns idempotently
 * and then zeroes them, matching the intent of migrations 100002 +
 * 100009.
 *
 * Down() drops the columns only if THIS migration added them — a
 * plain drop would break tenants that already had them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $addedGeo = false;
        $addedIp = false;

        if (! Schema::hasColumn('attendance_devices', 'enforce_geo')) {
            Schema::table('attendance_devices', function (Blueprint $table) {
                $table->boolean('enforce_geo')->default(false)->after('allowed_radius_meters');
            });
            $addedGeo = true;
        }

        if (! Schema::hasColumn('attendance_devices', 'enforce_ip')) {
            Schema::table('attendance_devices', function (Blueprint $table) {
                $table->boolean('enforce_ip')->default(false)->after('ip_whitelist');
            });
            $addedIp = true;
        }

        // Now safe to unconditionally reset. If the columns already
        // existed with non-false values, this is the same effect as
        // migration 100009 (blanket disable).
        DB::table('attendance_devices')->update([
            'enforce_geo' => false,
            'enforce_ip' => false,
        ]);

        // Record what we added so down() only drops what up() added.
        if ($addedGeo || $addedIp) {
            DB::table('migrations')->insert([
                'migration' => 'self_heal_enforce_columns_added_'.($addedGeo ? 'geo' : '').($addedIp ? '_ip' : ''),
                'batch' => (int) DB::table('migrations')->max('batch'),
            ]);
        }
    }

    public function down(): void
    {
        // Non-reversible on purpose — dropping columns another tenant
        // may already have relied on would break their scan config.
    }
};
