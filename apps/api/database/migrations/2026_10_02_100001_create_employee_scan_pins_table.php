<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kept out of `employees` on purpose: Employee is Scout-searchable and
 * serialised by many resources, so a hash living on that row would be one
 * careless `toArray()` away from leaking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_scan_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->string('pin_hash');
            $table->string('set_via', 20);
            // nullOnDelete: removing the admin account must not erase the
            // employee's working PIN.
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_scan_pins');
    }
};
