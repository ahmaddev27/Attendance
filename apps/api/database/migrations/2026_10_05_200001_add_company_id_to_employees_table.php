<?php

use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft company scoping, Phase B — step 1: give every employee a direct
 * company_id FK so the admin "scope to company" filter can be a single
 * indexed WHERE clause everywhere, rather than a three-level join through
 * teams -> departments -> companies on every list query.
 *
 * company_id is NULLABLE on purpose: not every employee has a team today
 * (bootstrap super-admin, unassigned new hires), and the Soft Scoping
 * design treats null as "no scope set" — the row stays visible in the
 * global list. Explicit nullOnDelete so wiping a Company does not cascade
 * into Employee rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('team_id')
                ->constrained('companies')
                ->nullOnDelete();

            $table->index('company_id');
        });

        $this->backfillCompanyIdFromTeamChain();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop the FK first — SQLite tolerates its omission, MySQL
            // demands the FK be released before the column disappears.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['company_id']);
            }
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }

    /**
     * Walk employees in modest chunks and set company_id from the team's
     * department. Done in PHP rather than a raw UPDATE ... JOIN ... so the
     * same code path works on both the MySQL production database and the
     * SQLite in-memory database the test suite uses (SQLite's UPDATE-FROM
     * dialect is incompatible with MySQL's multi-table UPDATE). The
     * population is a one-shot migration against a dataset in the low
     * thousands at most, so the per-row cost is irrelevant and the
     * clarity wins.
     */
    private function backfillCompanyIdFromTeamChain(): void
    {
        Employee::query()
            ->whereNotNull('team_id')
            ->whereNull('company_id')
            ->with('team.department:id,company_id')
            ->chunkById(500, function ($employees): void {
                foreach ($employees as $employee) {
                    $companyId = $employee->team?->department?->company_id;
                    if ($companyId !== null) {
                        DB::table('employees')
                            ->where('id', $employee->id)
                            ->update(['company_id' => $companyId]);
                    }
                }
            });
    }
};
