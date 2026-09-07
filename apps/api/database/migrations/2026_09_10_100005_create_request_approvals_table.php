<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();

            // restrictOnDelete: this is the audit trail for the step —
            // once a step has been decided on, the step definition it
            // pointed at cannot be deleted out from under that history.
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->restrictOnDelete();

            // Who took the action. restrictOnDelete for the same audit
            // reason as workflow_step_id above — employees are
            // soft-deleted in this codebase (see Employee::class), so a
            // hard delete reaching this constraint should be rare.
            $table->foreignId('approver_id')->constrained('employees')->restrictOnDelete();

            $table->enum('action', ['approved', 'rejected', 'returned', 'forwarded']);
            $table->text('comment')->nullable();

            // Only set when action = forwarded.
            $table->foreignId('forwarded_to_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->timestamp('decided_at');

            // Approval records are an immutable audit log — created_at
            // only, no updated_at. See App\Models\RequestApproval::UPDATED_AT.
            $table->timestamp('created_at')->nullable();

            $table->index(['request_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
    }
};
