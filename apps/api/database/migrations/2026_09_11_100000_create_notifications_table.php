<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard `notifications` table (normally published via
 * `php artisan notifications:table`) — every Notification class in
 * app/Notifications/ that sends through the `database` channel writes
 * here. No milestone before M7 needed it, so it did not exist yet;
 * dated one tick before the 2026_09_11_1000xx block M7 otherwise owns so
 * it is unmistakably "create the table before seeding data into it."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
