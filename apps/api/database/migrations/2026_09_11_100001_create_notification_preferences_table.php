<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user opt-out of a given notification type on a given channel. A
 * missing row means "enabled" (see NotificationPreferenceRepository::
 * isEnabled()) — rows are only ever written when a user actually
 * disables something, so a fresh user needs no seeding to have every
 * channel enabled by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification_type', 100);
            $table->enum('channel', ['database', 'sms', 'email', 'broadcast']);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'notification_type', 'channel'], 'notif_pref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
