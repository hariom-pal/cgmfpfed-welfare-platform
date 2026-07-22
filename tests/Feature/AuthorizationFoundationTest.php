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

        $labels = collect(app(MenuBuilder::class)->buildFor($vle))->pluck('label')->all();

        $this->assertContains('Add Application', $labels);
        $this->assertContains('Application', $labels);
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

        $labels = collect(app(MenuBuilder::class)->buildFor($admin))->pluck('label')->all();

        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Application', $labels);
        $this->assertContains('Batches', $labels);
        $this->assertContains('Payment', $labels);
        $this->assertContains('Samiti Wise Count', $labels);
        $this->assertContains('Master Management', $labels);
    }

    public function test_master_management_is_super_admin_only_even_when_other_roles_have_permission(): void
    {
        $districtUnion = $this->legacyUser(2);
        $this->grant($districtUnion, [35]);

        $this->assertFalse(app(PermissionService::class)->can($districtUnion, 'masters.manage'));

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
            'is_draft' => false,
            'student_aadhaar' => fake()->numerify('############'),
            'aadhaar_verified_student_name' => fake()->name(),
            'student_name' => fake()->name(),
            'created_by' => 1,
            'updated_by' => 1,
        ], $overrides));
    }
}
