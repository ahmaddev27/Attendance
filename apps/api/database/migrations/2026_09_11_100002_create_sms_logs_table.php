<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per SMS send attempt. Written by SendSmsJob (async path) and
 * SmsService::sendNow() (sync path).
 *
 * The row exists regardless of outcome — a `failed` row with an `error`
 * and the provider's `raw_response` is what makes a "SMS never arrived"
 * ticket debuggable. `to` is indexed so support can look a recipient up
 * quickly; `status` is indexed for the ops dashboard's error-rate query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('to')->index();
            $table->text('body');
            // Kept as a string column (not an enum) so a new status
            // value later — e.g. `queued`, `delivered` from a DLR
            // webhook — doesn't need an ALTER TABLE on a hot log table.
            $table->string('status', 20)->index();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
