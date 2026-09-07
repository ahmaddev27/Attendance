<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();

            $table->integer('step_order');
            $table->string('name', 150);

            $table->enum('approver_type', [
                'direct_manager',
                'department_manager',
                'specific_employee',
                'specific_role',
                'form_field',
            ]);

            // employee_id / role name / form field name depending on
            // approver_type — see App\Shared\Enums\ApproverType.
            $table->string('approver_ref', 100)->nullable();

            $table->boolean('can_reject')->default(true);
            $table->boolean('can_return')->default(false);
            $table->boolean('can_forward')->default(false);

            // Soft deadline for this step — no automated enforcement yet,
            // reserved for a future SLA/escalation milestone.
            $table->integer('sla_hours')->nullable();

            $table->timestamps();

            $table->unique(['workflow_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
