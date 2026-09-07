<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic key/value application settings, ported from the v1 pattern.
 * M7 uses it to hold the MTC SMS credentials (sms_username, sms_password,
 * sms_sender) — sms_password is stored encrypted, see
 * App\Modules\Notifications\Services\SettingsService.
 *
 * No prior module created this table, so it is created here rather than
 * merely "added to" — the migration is named for what M7 needs it for,
 * per the coordination note in the M7 spec.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'encrypted', 'boolean', 'integer', 'json'])->default('string');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
