<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-requested employee file fields (2026-10-06):
 *   - `national_id`              : the person's national ID number. Unique
 *                                   because one physical person = one ID; the
 *                                   constraint prevents accidentally creating
 *                                   two employee rows for the same person.
 *                                   Nullable because employees onboarded
 *                                   before the field was collected have none
 *                                   on file.
 *   - `national_id_image_path`   : storage path on the PRIVATE `local` disk
 *                                   for the uploaded ID scan. Never public —
 *                                   downloads go through a signed route.
 *   - `employment_contract_path` : storage path on the PRIVATE `local` disk
 *                                   for the signed employment contract. Same
 *                                   signed-download pattern as the ID image.
 *
 * Separate migration (rather than editing the original employees migration)
 * because the table is populated in production — altering history is unsafe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('national_id', 50)->nullable()->after('birth_date');
            $table->string('national_id_image_path', 255)->nullable()
                ->after('national_id');
            $table->string('employment_contract_path', 255)->nullable()
                ->after('national_id_image_path');

            // One physical person = one national ID. Nullable rows do NOT
            // participate in the uniqueness check (ANSI SQL behaviour, which
            // both MySQL and SQLite honour) so legacy employees without an
            // ID on file remain writable.
            $table->unique('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop the unique index first — MySQL demands it be released
            // before the backing column disappears; SQLite tolerates the
            // explicit drop too.
            $table->dropUnique(['national_id']);
            $table->dropColumn([
                'national_id',
                'national_id_image_path',
                'employment_contract_path',
            ]);
        });
    }
};
