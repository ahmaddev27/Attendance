<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 20)->unique();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // restrictOnDelete rather than cascade/null: a request type
            // with historical requests against it must be deactivated
            // (is_active = false), not deleted — mirrors
            // leave_requests.leave_type_id in the Leaves module.
            $table->foreignId('request_type_id')->constrained('request_types')->restrictOnDelete();

            $table->json('form_data');

            $table->enum('status', [
                'draft',
                'submitted',
                'pending',
                'approved',
                'rejected',
                'returned',
                'cancelled',
                'completed',
            ])->default('draft');

            // Nullable + nullOnDelete: current_step_id is only a pointer
            // into the request's own workflow's steps, always cleared
            // once the request reaches a terminal state (see
            // ApprovalService). A workflow_steps row being removed (e.g.
            // an admin deleting a retired step) should not block that
            // delete or corrupt this request — it should just fall back
            // to "no active step".
            $table->foreignId('current_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('status');
            $table->index('current_step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
