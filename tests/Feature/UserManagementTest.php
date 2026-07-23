<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\DistrictUnion;
use App\Models\Samiti;
use App\Models\User;
use App\Services\MenuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_and_routes_are_hidden_without_permission(): void
    {
        $samiti = $this->staffUser(3);

        $labels = collect(app(MenuBuilder::class)->buildFor($samiti))->pluck('label')->all();
        $this->assertNotContains('User Management', $labels);

        $this->actingAs($samiti);
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
    }

    public function test_super_admin_can_view_and_create_users(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);

        $labels = collect(app(MenuBuilder::class)->buildFor($admin))->pluck('label')->all();
        $this->assertContains('User Management', $labels);

        $this->actingAs($admin);
        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.create'))->assertOk();
    }

    public function test_creating_a_samiti_user_requires_district_union_and_samiti(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $districtUnion = DistrictUnion::factory()->create(['legacy_id' => 501]);
        $samiti = Samiti::factory()->create(['legacy_id' => 601, 'district_union_id' => $districtUnion->id]);

        $response = $this->post(route('users.store'), [
            'name' => 'Samiti Officer',
            'email' => 'samiti.officer@example.test',
            'mobile' => '9876543210',
            'user_type' => 3,
            'password' => 'Passw0rd!23',
            'password_confirmation' => 'Passw0rd!23',
            'status' => '1',
            'district_union_id' => $districtUnion->id,
            'samiti_id' => $samiti->id,
        ]);

        $response->assertRedirect(route('users.index'));

        $created = User::where('email', 'samiti.officer@example.test')->first();
        $this->assertNotNull($created);
        $this->assertSame(3, $created->user_type);
        $this->assertSame($districtUnion->id, $created->district_union_master_id);
        $this->assertSame($districtUnion->legacy_id, $created->districtunion);
        $this->assertSame($samiti->id, $created->samiti_master_id);
        $this->assertSame($samiti->legacy_id, $created->samiti);
        $this->assertSame('1', $created->reset_code);
        $this->assertTrue(Hash::check('Passw0rd!23', $created->password));
    }

    public function test_creating_a_circle_user_requires_circle_and_district_union(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $circle = Circle::query()->create([
            'uuid' => (string) Str::uuid(),
            'legacy_id' => 9,
            'legacy_code' => 'C9',
            'name' => 'Test Circle',
            'is_active' => true,
        ]);
        $districtUnion = DistrictUnion::factory()->create(['legacy_id' => 801, 'circle_id' => $circle->id]);

        $response = $this->post(route('users.store'), [
            'name' => 'Circle Officer',
            'email' => 'circle.officer@example.test',
            'mobile' => '9876511111',
            'user_type' => 5,
            'password' => 'Passw0rd!23',
            'password_confirmation' => 'Passw0rd!23',
            'status' => '1',
            'circle_id' => $circle->id,
            'district_union_id' => $districtUnion->id,
        ]);

        $response->assertRedirect(route('users.index'));

        $created = User::where('email', 'circle.officer@example.test')->first();
        $this->assertNotNull($created);
        $this->assertSame(5, $created->user_type);
        $this->assertSame($circle->id, $created->circle_master_id);
        $this->assertSame($circle->legacy_id, $created->circle);
    }

    public function test_creating_a_samiti_user_without_samiti_fails_validation(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $districtUnion = DistrictUnion::factory()->create();

        $response = $this->post(route('users.store'), [
            'name' => 'Samiti Officer',
            'email' => 'samiti.missing@example.test',
            'mobile' => '9876500000',
            'user_type' => 3,
            'password' => 'Passw0rd!23',
            'password_confirmation' => 'Passw0rd!23',
            'status' => '1',
            'district_union_id' => $districtUnion->id,
        ]);

        $response->assertSessionHasErrors('samiti_id');
        $this->assertDatabaseMissing('users', ['email' => 'samiti.missing@example.test']);
    }

    public function test_super_admin_and_vle_roles_cannot_be_created_via_this_screen(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $districtUnion = DistrictUnion::factory()->create();

        $asSuperAdmin = $this->post(route('users.store'), [
            'name' => 'Would Be Admin',
            'email' => 'would.be.admin@example.test',
            'mobile' => '9876500001',
            'user_type' => 1,
            'password' => 'Passw0rd!23',
            'password_confirmation' => 'Passw0rd!23',
            'status' => '1',
            'district_union_id' => $districtUnion->id,
        ]);
        $asSuperAdmin->assertSessionHasErrors('user_type');

        $asVle = $this->post(route('users.store'), [
            'name' => 'Would Be VLE',
            'email' => 'would.be.vle@example.test',
            'mobile' => '9876500002',
            'user_type' => (int) config('csc.vle_role_id'),
            'password' => 'Passw0rd!23',
            'password_confirmation' => 'Passw0rd!23',
            'status' => '1',
            'district_union_id' => $districtUnion->id,
        ]);
        $asVle->assertSessionHasErrors('user_type');

        $this->assertDatabaseMissing('users', ['email' => 'would.be.admin@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'would.be.vle@example.test']);
    }

    public function test_existing_super_admin_and_vle_accounts_cannot_be_edited_via_this_screen(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $otherSuperAdmin = $this->staffUser(1);
        $vleUser = User::factory()->create(['user_type' => (int) config('csc.vle_role_id'), 'csc_id' => (string) Str::uuid()]);

        $this->get(route('users.edit', $otherSuperAdmin))->assertForbidden();
        $this->get(route('users.edit', $vleUser))->assertForbidden();
        $this->patch(route('users.toggle', $otherSuperAdmin))->assertForbidden();
    }

    public function test_editing_a_user_cannot_change_role_but_can_change_geography_and_status(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $originalDu = DistrictUnion::factory()->create(['legacy_id' => 701]);
        $newDu = DistrictUnion::factory()->create(['legacy_id' => 702]);
        $target = $this->staffUser(2, ['district_union_master_id' => $originalDu->id, 'districtunion' => $originalDu->legacy_id]);

        $response = $this->put(route('users.update', $target), [
            'status' => '0',
            'district_union_id' => $newDu->id,
        ]);

        $response->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame(2, $target->user_type);
        $this->assertSame('0', $target->status);
        $this->assertSame($newDu->id, $target->district_union_master_id);
        $this->assertSame($newDu->legacy_id, $target->districtunion);
    }

    public function test_toggle_flips_status(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $target = $this->staffUser(2, ['status' => '1']);

        $this->patch(route('users.toggle', $target))->assertRedirect();
        $this->assertSame('0', $target->refresh()->status);

        $this->patch(route('users.toggle', $target))->assertRedirect();
        $this->assertSame('1', $target->refresh()->status);
    }

    public function test_index_excludes_super_admin_and_vle_and_supports_search(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $otherSuperAdmin = $this->staffUser(1, ['name' => 'Findable Super Admin Two']);
        $vle = User::factory()->create(['user_type' => (int) config('csc.vle_role_id'), 'name' => 'Findable VLE Operator', 'csc_id' => (string) Str::uuid()]);
        $samiti = $this->staffUser(3, ['name' => 'Findable Samiti Officer']);

        $response = $this->get(route('users.index', ['name' => 'Findable']));
        $response->assertOk();
        $response->assertSee('Findable Samiti Officer');
        $response->assertDontSee('Findable VLE Operator');
        $response->assertDontSee('Findable Super Admin Two');
    }

    public function test_csv_export_is_configurable_via_settings(): void
    {
        $admin = $this->staffUser(1);
        $this->grant($admin, [1, 2]);
        $this->actingAs($admin);

        $target = $this->staffUser(2, ['name' => 'Export Sample Officer']);

        $this->get(route('settings.csv-export-configuration.index'))->assertOk()->assertSee('User Management');
        $this->get(route('settings.csv-export-configuration.edit', 'users'))->assertOk()->assertSee('Role');

        $response = $this->get(route('users.export'));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Export Sample Officer', $csv);
        $this->assertStringContainsString('District Union', $csv);
    }

    private function staffUser(int $roleId, array $overrides = []): User
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
}
