<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standard Laravel notifications table (M7). Shape matches
 * `php artisan notifications:table` so we plug into the Notifiable trait
 * on User without a custom channel implementation:
 *   - id       : UUID (Laravel::notify() generates and Filament/Nova assume UUID)
 *   - type     : notification class FQCN
 *   - notifiable_type/id : morph to User
 *   - data     : JSON payload (our TaqatNotification writes {title, body, url, icon})
 *   - read_at  : null until the recipient opens it
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
