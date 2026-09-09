<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scale audit follow-up: three composite indexes the query planner needs
 * once the tables grow past a few thousand rows.
 *
 *  - attendances(employee_id, status, date): the reports/attendance list
 *    filters on all three; the previous single-column employee_id index
 *    forced a range scan + filesort on the status/date pair.
 *  - notifications(notifiable_type, notifiable_id, read_at, created_at):
 *    the inbox query orders unread first by created_at desc. The old
 *    3-column index left created_at out and MySQL fell back to filesort
 *    once a user accumulated >500 rows.
 *  - request_approvals(forwarded_to_id, action, decided_at): the
 *    "forwarded to me" inbox tab. Previously scanned the whole approval
 *    log filtering forwarded_to_id in PHP.
 *
 * `down()` restores the original indexes so a rollback leaves the
 * schema in the exact shape the base migrations produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['employee_id', 'status', 'date'], 'idx_att_emp_status_date');
            // Drop the redundant single-column index on employee_id — the FK
            // still enforces it, and the composite above supersedes it.
            $table->dropIndex('attendances_employee_id_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_type_notifiable_id_read_at_index');
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'idx_notif_inbox'
            );
        });

        Schema::table('request_approvals', function (Blueprint $table) {
            $table->index(
                ['forwarded_to_id', 'action', 'decided_at'],
                'idx_appr_forwarded'
            );
        });
    }

    public function down(): void
    {
        Schema::table('request_approvals', function (Blueprint $table) {
            $table->dropIndex('idx_appr_forwarded');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notif_inbox');
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_notifiable_type_notifiable_id_read_at_index'
            );
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index('employee_id', 'attendances_employee_id_index');
            $table->dropIndex('idx_att_emp_status_date');
        });
    }
};
