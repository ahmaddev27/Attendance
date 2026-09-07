<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for every outgoing SMS sent via the MTC gateway (ported
 * from v1 — see _v1_artifacts/mtc/). `notifiable_type`/`notifiable_id`
 * are a loose, nullable polymorphic pointer to whatever triggered the
 * message (a User, an Employee, ...) rather than a real morphs()
 * relation with a matching FK — an SMS log must survive the source
 * record being deleted, so it is deliberately not constrained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->text('message');
            $table->enum('status', ['sent', 'failed']);
            $table->text('provider_response')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('sent_at');
            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('status');
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
