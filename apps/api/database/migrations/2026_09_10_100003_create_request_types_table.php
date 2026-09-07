<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();

            // lucide icon name for the UI — not validated against an
            // actual icon set here, this table doesn't know about the
            // frontend's icon library.
            $table->string('icon')->nullable();
            $table->string('color', 7)->default('#2678C4');

            // restrictOnDelete rather than cascade: a workflow already
            // backing one or more request types must be deactivated
            // (is_active = false), not deleted — see
            // WorkflowService::delete() for the application-level guard
            // that keeps a delete attempt from ever reaching this
            // constraint in the first place.
            $table->foreignId('workflow_id')->constrained('workflows')->restrictOnDelete();

            // Array of field definitions — see the docblock on
            // App\Models\RequestType for the documented shape.
            $table->json('form_schema');

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_types');
    }
};
