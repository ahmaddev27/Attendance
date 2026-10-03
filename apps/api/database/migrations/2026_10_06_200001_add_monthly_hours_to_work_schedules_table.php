<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table): void {
            // Nullable: a legacy schedule without a monthly target is explicitly
            // "no target set" rather than zero — the attendance report falls
            // back to not colouring the hours cell when this is null.
            $table->decimal('monthly_hours', 6, 2)->nullable()->after('min_hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table): void {
            $table->dropColumn('monthly_hours');
        });
    }
};
