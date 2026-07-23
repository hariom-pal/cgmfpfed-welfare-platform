<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrects 2026_07_24_000100_add_account_role: that migration granted role 6 ("Account")
 * `role_priviledge` permission 38 ("Manage Batch") for Workflow Batches menu parity with
 * roles 1/2/4/5. Re-verification against `scholarship.sql`'s actual `role_priviledge` INSERT
 * (line 1062) shows role_id=6 has zero rows in production legacy data — permission 38 there
 * is held only by roles 1, 2, 4, 5. Granting it to role 6 was therefore an invented permission,
 * not a legacy-derived one, and violates "apply permissions exactly as legacy CI3".
 *
 * Legacy's Account role has no menu presence at all (no Batches, no Payment item — both are
 * gated by permission 38 or a literal USER_TYPE==1 check that role 6 never satisfies); its only
 * access is the hardcoded USER_TYPE=='6' gate on Scholarship::remove()/forward(), reachable
 * only by a directly-typed URL. Removing this row restores that exact "headless, no menu"
 * shape. Route-level access to the Laravel equivalent (ScholarshipWorkflowController::action(),
 * which implements the 15/16->28 "forward" transition) is preserved separately via role 6's
 * membership in config('legacy_authorization.abilities')'s workflow.view/workflow.action
 * `roles` lists — a faithful translation of the hardcoded USER_TYPE check, not the
 * role_priviledge permission system, so it is unaffected by this correction.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_priviledge')->where('role_id', 6)->where('permission_id', 38)->delete();
    }

    public function down(): void
    {
        $nextId = ((int) DB::table('role_priviledge')->max('id')) + 1;
        DB::table('role_priviledge')->updateOrInsert(
            ['role_id' => 6, 'permission_id' => 38],
            ['id' => $nextId],
        );
    }
};
