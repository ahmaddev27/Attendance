<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product decision: every request / leave approval routes to the
 * super-admin (or any user carrying that role) instead of the
 * requester's direct manager. Rationale from the customer: the small
 * team doesn't have real managers wired into the org tree yet, and
 * routing to a non-existent manager was silently dead-ending requests
 * in an inbox no one owned.
 *
 * Every workflow step whose approver_type was `direct_manager` is
 * repointed to `specific_role` with `approver_ref = super-admin` —
 * ApproverResolver already resolves that path (see
 * ApproverResolver::resolveByRole) to the collection of users who
 * hold the role. No behavior change for other approver_types.
 *
 * down() reverses the direction only for rows still tagged with the
 * `super-admin` marker, so a mixed post-migration state (an admin
 * later flipped some steps back to specific_role for a different
 * role) is left intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workflow_steps')
            ->where('approver_type', 'direct_manager')
            ->update([
                'approver_type' => 'specific_role',
                'approver_ref' => 'super-admin',
            ]);
    }

    public function down(): void
    {
        DB::table('workflow_steps')
            ->where('approver_type', 'specific_role')
            ->where('approver_ref', 'super-admin')
            ->update([
                'approver_type' => 'direct_manager',
                'approver_ref' => null,
            ]);
    }
};
