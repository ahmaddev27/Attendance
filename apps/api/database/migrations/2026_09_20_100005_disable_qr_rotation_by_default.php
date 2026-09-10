<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QR tokens are now permanent by policy: they expire only when the
 * device row is deleted or the token is manually rotated by an admin.
 *
 * - Default for new devices flips from 300s → 0 (0 = never rotate).
 * - Every existing device with the old default (300) gets set to 0 so
 *   posters printed today keep working forever without an admin edit.
 *   Devices an admin explicitly set to a non-default window (say 60 or
 *   3600) are left alone — that was a deliberate choice we shouldn't
 *   silently reverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->unsignedInteger('qr_rotates_every_seconds')->default(0)->change();
        });

        DB::table('attendance_devices')
            ->where('qr_rotates_every_seconds', 300)
            ->update(['qr_rotates_every_seconds' => 0]);
    }

    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->unsignedInteger('qr_rotates_every_seconds')->default(300)->change();
        });
    }
};
