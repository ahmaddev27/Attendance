<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When recruitment:scan-sla last announced a breach of the job's current
 * stage. The hourly scan used to notify on every run; with this it announces
 * a breach once per stage and repeats at most daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_requirements', function (Blueprint $table) {
            $table->timestamp('sla_breach_notified_at')->nullable()->after('stage_entered_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_requirements', function (Blueprint $table) {
            $table->dropColumn('sla_breach_notified_at');
        });
    }
};
