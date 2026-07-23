<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds role 6 ("Account"), referenced by DataScopeService's existing
 * `workflow_stage = accounts` scoping but never actually defined as an
 * assignable role — confirmed against the legacy `user_type`/`role_priviledge`
 * dumps to have no real production data of its own (see ROLE_WORKFLOW_DOCUMENT.md,
 * "Known Differences", for the reasoning behind the permission grant below).
 *
 * Uses a migration (not a seeder) so this reference row is guaranteed present in
 * every environment `php artisan migrate` runs in, including test databases under
 * RefreshDatabase, which do not run seeders.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_type')->updateOrInsert(['id' => 6], ['type' => 'Account']);

        // Permission 38 ("Manage Batch") is what MenuBuilder checks directly (bypassing the
        // config-driven ability system) to show the "Workflow Batches" menu item to roles
        // 1-5 — granted here so Account has the same menu parity as the other workflow roles.
        $nextId = ((int) DB::table('role_priviledge')->max('id')) + 1;
        DB::table('role_priviledge')->updateOrInsert(
            ['role_id' => 6, 'permission_id' => 38],
            ['id' => $nextId],
        );
    }

    public function down(): void
    {
        DB::table('role_priviledge')->where('role_id', 6)->where('permission_id', 38)->delete();
        DB::table('user_type')->where('id', 6)->delete();
    }
};
