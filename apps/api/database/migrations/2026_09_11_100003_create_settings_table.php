<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value settings store, editable by admins from /admin/settings.
 *
 * Used for runtime configuration that shouldn't require a redeploy:
 *   - Mail: RESEND_KEY, MAIL_FROM_ADDRESS, MAIL_FROM_NAME
 *   - SMS:  MTC_SMS_USERNAME, MTC_SMS_PASSWORD, MTC_SMS_SENDER, MTC_SMS_ENDPOINT, MTC_SMS_FAKE
 *
 * `encrypted=1` values are stored via Crypt::encryptString() and decrypted
 * lazily by SettingsService — never touch the raw column outside the service.
 * `group` is a UI-only bucket (mail / sms / ...) that makes rendering the
 * settings form cleaner without a second lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->string('group', 40)->default('general')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
