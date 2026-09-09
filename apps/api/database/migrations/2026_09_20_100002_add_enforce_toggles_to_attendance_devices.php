<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two boolean toggles that let each device opt into geo-fence + IP-whitelist
 * enforcement independently. The lat/lng/radius/ip_whitelist columns
 * already exist — this is just the on/off flag so admins can save the
 * settings without enforcing them yet (staging), then flip them on.
 *
 * Both default to false so existing devices keep their current behaviour
 * (which was: enforce whenever a coord/whitelist was set — now only when
 * the toggle is explicitly on).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->boolean('enforce_geo')->default(false)->after('allowed_radius_meters');
            $table->boolean('enforce_ip')->default(false)->after('ip_whitelist');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['enforce_geo', 'enforce_ip']);
        });
    }
};
