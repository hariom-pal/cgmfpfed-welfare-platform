<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\MenuBuilder;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuthorizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_samiti_role_must_select_scheme_before_application_listing(): void
    {
        $this->actingAs($this->legacyUser(3, ['districtunion' => 7, 'samiti' => 77]));

        Scheme::factory()->create(['is_active' => true]);

        $this->get(route('applications.index'))->assertOk()->assertSee('Select a Scheme to view applications');
    }

    public function test_vle_menu_and_visitor_middleware_match_application_only_access(): void
    {
        $vle = $this->legacyUser((int) config('csc.vle_role_id'), ['csc_id' => '313676900017']);

        $menu = collect(app(MenuBuilder::class)->buildFor($vle));
        $labels = $menu->pluck('label')->all();
        $scholarshipChildren = collect($menu->firstWhere('label', 'Scholarship Applications')['children'] ?? [])->pluck('label')->all();

        $this->assertContains('Scholarship Applications', $labels);
        $this->assertContains('Add Application', $scholarshipChildren);
        $this->assertNotContains('Payment', $labels);
        $this->assertNotContains('Settings', $labels);

        $this->actingAs($vle);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('workflow.index'))->assertForbidden();
    }

    public function test_super_admin_menu_contains_production_admin_items(): void
    {
        $admin = $this->legacyUser(1);
        $this->grant($admin, [1, 2, 4, 35, 38]);

        $menu = collect(app(MenuBuilder::class)->buildFor($admin));
        $labels = $menu->pluck('label')->all();
        $masterChildren = collect($menu->firstWhere('label', 'Masters')['children'] ?? [])->pluck('label')->all();
        $scholarshipChildren = collect($menu->firstWhere('label', 'Scholarship Applications')['children'] ?? [])->pluck('label')->all();
        $beemaChildren = collect($menu->firstWhere('label', 'Beema')['children'] ?? [])->pluck('label')->all();
        $otherChildren = collect($menu->firstWhere('label', 'Other Modules')['children'] ?? [])->pluck('label')->all();

        $this->assertSame('Dashboard', $labels[0]);
        $this->assertSame('Masters', $labels[1]);
        $this->assertSame(['Dashboard', 'Masters', 'Scholarship Applications', 'Beema', 'Reports', 'User Management', 'Administration', 'Other Modules'], $labels);
        $this->assertContains('Scholarship Applications', $labels);
        $this->assertContains('Beema', $labels);
        $this->assertContains('Reports', $labels);
        $this->assertContains('User Management', $labels);
        $this->assertContains('Administration', $labels);
        $this->assertContains('Other Modules', $labels);

        $administrationChildren = collect($menu->firstWhere('label', 'Administration')['children'] ?? [])->pluck('label')->all();
        $this->assertContains('CSV Export Configuration', $administrationChildren);
        $this->assertSame(route('export-templates.index'), collect($menu->firstWhere('label', 'Administration')['children'])->firstWhere('label', 'CSV Export Configuration')['url']);
        $this->assertSame(
            collect(config('masters'))->pluck('label')->map(fn (?string $label, string $key): string => $label ?? str($key)->headline()->toString())->values()->all(),
            $masterChildren,
        );
        $this->assertNotContains('Masters', $scholarshipChildren);
        $this->assertNotContains('Masters', $beemaChildren);
        $this->assertContains('Workflow Batches', $otherChildren);
        $this->assertContains('Payment', $otherChildren);
    }

    public function test_scholarship_menu_contains_status_filtered_views(): void
    {
        $admin = $this->legacyUser(1);
        $this->grant($admin, [1, 2, 4, 35, 38]);

        $menu = collect(app(MenuBuilder::class)->buildFor($admin));
        $children = collect($menu->firstWhere('label', 'Scholarship Applications')['children'] ?? []);

        $this->assertSame(
            ['All Applications', 'Pending', 'Pending at VLE', 'Rejected', 'Completed', 'Payment Failed'],
            $children->pluck('label')->all(),
        );
        $this->assertSame(route('applications.index', ['status' => 'pending']), $children->firstWhere('label', 'Pending')['url']);
        $this->assertSame(route('applications.index', ['status' => 'pending_vle']), $children->firstWhere('label', 'Pending at VLE')['url']);
        $this->assertSame(route('applications.index', ['status' => 'rejected']), $children->firstWhere('label', 'Rejected')['url']);
        $this->assertSame(route('applications.index', ['status' => 'completed']), $children->firstWhere('label', 'Completed')['url']);
        $this->assertSame(route('applications.index', ['status' => 'payment_failed']), $children->firstWhere('label', 'Payment Failed')['url']);
    }

    public function test_master_management_is_super_admin_only_even_when_other_roles_have_permission(): void
    {
        $districtUnion = $this->legacyUser(2);
        $this->grant($districtUnion, [35]);

        $this->assertFalse(app(PermissionService::class)->can($districtUnion, 'masters.manage'));
        $labels = collect(app(MenuBuilder::class)->buildFor($districtUnion))->pluck('label')->all();
        $this->assertNotContains('Masters', $labels);

        $this->actingAs($districtUnion);
        $this->get(route('masters.index', 'schemes'))->assertForbidden();
    }

    public function test_permission_service_uses_matrix_vle_rules_without_duplicate_db_rows(): void
    {
        $vle = $this->legacyUser((int) config('csc.vle_role_id'));

        $this->assertTrue(app(PermissionService::class)->has($vle, 5));
        $this->assertFalse(app(PermissionService::class)->has($vle, 38));
        $this->assertTrue(app(PermissionService::class)->can($vle, 'applications.view'));
        $this->assertFalse(app(PermissionService::class)->can($vle, 'workflow.view'));
    }

    public function test_data_scope_centralizes_role_visibility(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $owner = $this->legacyUser((int) config('csc.vle_role_id'));
        $other = $this->legacyUser((int) config('csc.vle_role_id'));
        $du = $this->legacyUser(2, ['districtunion' => 5]);

        $owned = $this->application($session->id, $scheme->id, [
            'applicant_user_id' => $owner->id,
            'district_union_id' => 5,
        ]);
        $paired = $this->application($session->id, $scheme->id, [
            'applicant_user_id' => $other->id,
            'district_union_id' => 32,
        ]);
        $hidden = $this->application($session->id, $scheme->id, [
            'applicant_user_id' => $other->id,
            'district_union_id' => 9,
        ]);

        $scope = app(DataScopeService::class);

        $this->assertTrue($scope->canViewScholarshipApplication($owner, $owned));
        $this->assertFalse($scope->canViewScholarshipApplication($owner, $paired));
        $this->assertTrue($scope->canViewScholarshipApplication($du, $owned));
        $this->assertTrue($scope->canViewScholarshipApplication($du, $paired));
        $this->assertFalse($scope->canViewScholarshipApplication($du, $hidden));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function legacyUser(int $roleId, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => '1',
            'user_type' => $roleId,
            'districtunion' => 1,
            'samiti' => 1,
            'circle' => 1,
        ], $overrides));
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function grant(User $user, array $permissionIds): void
    {
        $nextId = ((int) DB::table('role_priviledge')->max('id')) + 1;
        foreach ($permissionIds as $offset => $permissionId) {
            DB::table('role_priviledge')->updateOrInsert(
                ['role_id' => (int) $user->user_type, 'permission_id' => $permissionId],
                ['id' => $nextId + $offset],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function application(int $sessionId, int $schemeId, array $overrides = []): ScholarshipApplication
    {
        return ScholarshipApplication::query()->create(array_merge([
            'uuid' => fake()->uuid(),
            'application_number' => fake()->unique()->numerify('S##########'),
            'academic_session_id' => $sessionId,
            'scheme_id' => $schemeId,
            'status' => 0,
            'status_label' => 'Pending',
            'current_stage' => 'samiti',
            'application_state' => 'in_workflow',
            'submission_state' => 'submitted',
            'workflow_state' => 'pending_samiti',
            'workflow_stage' => 'samiti',
            'approval_state' => 'pending',
            'payment_state' => 'wallet_success',
            'is_draft' => false,
            'entered_workflow_at' => now(),
            'wallet_paid_at' => now(),
            'student_aadhaar' => fake()->numerify('############'),
            'aadhaar_verified_student_name' => fake()->name(),
            'student_name' => fake()->name(),
            'created_by' => 1,
            'updated_by' => 1,
        ], $overrides));
    }
}
