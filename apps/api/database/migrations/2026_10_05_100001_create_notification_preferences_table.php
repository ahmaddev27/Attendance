<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user opt-OUT matrix. Absence of a row means "enabled" so new users
 * default to the full fan-out TaqatNotification already describes — the
 * table only grows when a user actively mutes a (event, channel) pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Short string — the catalog in NotificationPreferenceService
            // bounds this to a few dozen known keys, so 80 is comfortably wide.
            $table->string('event_key', 80);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            // One row per (user, event, channel) tuple — the service upserts
            // on this triple, so this unique index is both the integrity
            // guard AND the lookup path when NotificationService::dispatch()
            // checks a channel.
            $table->unique(['user_id', 'event_key', 'channel'], 'notif_prefs_user_event_channel_unique');
            $table->index('user_id', 'notif_prefs_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
