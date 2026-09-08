<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `push_tokens` — one row per (user, device) that has opted in to receive
 * mobile push notifications through Expo. The mobile app registers on
 * every launch and de-registers on logout.
 *
 * `token` is the ExponentPushToken[...] string Expo hands back to the
 * device; it is the target of the /send API call. `device_id` is the
 * mobile app's local install id (kept stable across launches), used to
 * deduplicate multiple registrations from the same handset instead of
 * ballooning the table every time the user reopens the app.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token');
            $table->enum('platform', ['ios', 'android', 'web'])->default('android');
            // Mobile-app-local install id — one per handset, stays stable across
            // relaunches. NULL is allowed for legacy or non-mobile clients.
            $table->string('device_id')->nullable();
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // A single (user, device) has exactly one live token — the
            // register endpoint upserts on this constraint, replacing any
            // prior token if Expo rotated the value.
            $table->unique(['user_id', 'device_id']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
