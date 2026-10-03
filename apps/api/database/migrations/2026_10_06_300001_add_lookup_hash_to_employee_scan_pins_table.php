<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a keyed-HMAC lookup hash so the kiosk can resolve an employee by
 * PIN alone (no employee_number input). bcrypt is deterministic only when
 * salted — so it cannot be used to find a row by PIN. The HMAC uses a
 * server-held key (APP_KEY), is unique per PIN, and lets us keep bcrypt as
 * the at-rest hash. A UNIQUE index is what makes "PIN-only identity" safe:
 * two employees can never share a PIN.
 *
 * Nullable because pre-existing PIN rows have no plaintext available to
 * backfill with — admins must bulk-reissue after upgrade (feature already
 * exists on the scan-pins dialog). ScanPinService treats a NULL-lookup row
 * as "needs reissue" for PIN-only identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_scan_pins', function (Blueprint $table): void {
            $table->char('pin_lookup_hash', 64)->nullable()->after('pin_hash');
            $table->unique('pin_lookup_hash', 'employee_scan_pins_pin_lookup_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employee_scan_pins', function (Blueprint $table): void {
            $table->dropUnique('employee_scan_pins_pin_lookup_hash_unique');
            $table->dropColumn('pin_lookup_hash');
        });
    }
};
