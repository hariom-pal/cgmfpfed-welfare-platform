<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MasterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_admin_can_manage_a_master_record(): void
    {
        $this->actingAsMasterManager();

        $this->post(route('masters.store', 'courses'), [
            'code' => 'CRS-TEST',
            'name' => 'Test Course',
            'description' => 'Feature test course',
            'is_active' => '1',
        ])->assertRedirect(route('masters.index', 'courses'));

        $this->assertDatabaseHas('courses', [
            'code' => 'CRS-TEST',
            'name' => 'Test Course',
        ]);
    }

    public function test_enterprise_portal_pages_open_after_login(): void
    {
        $this->actingAsMasterManager();

        $this->get(route('dashboard'))->assertOk()->assertSee('Operational overview');
        $this->get(route('applications.index'))->assertOk()->assertSee('Select a Scheme to view applications');
        $this->get(route('workflow.index'))->assertOk()->assertSee('Scholarship Workflow');

        foreach (array_keys(config('masters')) as $masterKey) {
            $this->get(route('masters.index', $masterKey))->assertOk();
            $this->get(route('masters.create', $masterKey))->assertOk();
        }
    }

    public function test_scheme_can_be_linked_to_document_types(): void
    {
        $scheme = Scheme::factory()->create();
        $documentType = DocumentType::factory()->create();

        $scheme->documentTypes()->attach($documentType);

        $this->assertTrue($scheme->fresh()->documentTypes->contains($documentType));
    }

    private function actingAsMasterManager(): User
    {
        $user = User::factory()->create([
            'status' => '1',
            'user_type' => 1,
        ]);

        DB::table('role_priviledge')->insert([
            ['id' => 9001, 'role_id' => 1, 'permission_id' => 35],
            ['id' => 9002, 'role_id' => 1, 'permission_id' => 5],
            ['id' => 9003, 'role_id' => 1, 'permission_id' => 6],
            ['id' => 9004, 'role_id' => 1, 'permission_id' => 16],
        ]);

        $this->actingAs($user);

        return $user;
    }
}
