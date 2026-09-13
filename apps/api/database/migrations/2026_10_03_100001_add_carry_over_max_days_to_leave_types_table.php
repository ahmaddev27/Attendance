<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cap on how many unused days of a leave type roll into the next year.
 * NULL means nothing carries over, so the annual rollover never invents days
 * for a type that did not opt in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->decimal('carry_over_max_days', 5, 2)->nullable()->after('default_annual_entitlement');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('carry_over_max_days');
        });
    }
};
