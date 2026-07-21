<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Scheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MasterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_admin_can_manage_a_master_record(): void
    {
        $this->post(route('login.store'), [
            'username' => 'admin',
            'password' => 'admin123',
        ])->assertRedirect(route('dashboard'));

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
        $this->withSession(['local_admin_authenticated' => true]);

        $this->get(route('dashboard'))->assertOk()->assertSee('Operational overview');
        $this->get(route('applications.index'))->assertOk()->assertSee('Applications Module');
        $this->get(route('workflow.index'))->assertOk()->assertSee('Workflow Module');

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
}
