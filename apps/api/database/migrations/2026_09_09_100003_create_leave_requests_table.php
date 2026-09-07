<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // restrictOnDelete rather than cascade/null: a leave type with
            // historical requests against it must be deactivated
            // (is_active = false), not deleted — see
            // LeaveTypeService::delete() for the application-level guard
            // that keeps a delete attempt from ever reaching this
            // constraint in the first place.
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 2);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();

            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->default('draft');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Unused placeholder for the M5 Workflow module's approval-chain
            // integration — no FK constraint since that table doesn't exist
            // yet in this milestone.
            $table->unsignedBigInteger('workflow_instance_id')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
