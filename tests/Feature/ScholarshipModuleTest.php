<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\AcademicSession;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ScholarshipModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_submit_enforces_brd_rules_and_audits(): void
    {
        $user = $this->userWithPermissions();
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);

        $application = $this->service()->createDraft($this->validPayload($session->id, 1), $user);
        $submitted = $this->service()->submit($application, $user);

        $this->assertFalse($submitted->is_draft);
        $this->assertSame(ScholarshipApplicationStatus::Pending->value, $submitted->status);
        $this->assertSame('80.00', $submitted->percentage);
        $this->assertSame('2500.00', $submitted->amount);
        $this->assertNotNull($submitted->application_number);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $submitted->id,
            'action' => 'submitted',
        ]);
        $this->assertFalse(Schema::hasColumn('scholarship_applications', 'head_of_family_bank_account_number'));
    }

    public function test_duplicate_aadhaar_session_and_sibling_bank_reuse_are_rejected(): void
    {
        $user = $this->userWithPermissions();
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);

        $first = $this->service()->createDraft($this->validPayload($session->id, 1), $user);
        $this->service()->submit($first, $user);

        $this->expectException(ValidationException::class);
        $second = $this->service()->createDraft($this->validPayload($session->id, 1), $user);
        $this->service()->submit($second, $user);
    }

    public function test_bank_account_cannot_link_to_another_student_aadhaar(): void
    {
        $user = $this->userWithPermissions();
        $session2026 = AcademicSession::factory()->create(['name' => '2026-27']);
        $session2027 = AcademicSession::factory()->create(['name' => '2027-28']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);

        $first = $this->service()->createDraft($this->validPayload($session2026->id, 1), $user);
        $this->service()->submit($first, $user);

        $secondPayload = $this->validPayload($session2027->id, 1, [
            'student_aadhaar' => '222222222222',
            'student_name' => 'Second Student',
            'student_bank_account_holder_name' => 'Second Student',
        ]);

        $this->expectException(ValidationException::class);
        $second = $this->service()->createDraft($secondPayload, $user);
        $this->service()->submit($second, $user);
    }

    public function test_scheme_one_and_two_are_restricted_to_class_ten_or_twelve(): void
    {
        $user = $this->userWithPermissions();
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 2, 'code' => 'SCH2', 'name' => 'Merit Scholarship']);

        $application = $this->service()->createDraft($this->validPayload($session->id, 2, ['class' => '9']), $user);

        $this->expectException(ValidationException::class);
        $this->service()->submit($application, $user);
    }

    public function test_workflow_and_payment_actions_are_audited(): void
    {
        $user = $this->userWithPermissions();
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);

        $application = $this->service()->createDraft($this->validPayload($session->id, 1), $user);
        $application = $this->service()->submit($application, $user);
        $application = $this->service()->transition($application, 'recommend', 'Samiti verified', $user);

        $this->assertSame(ScholarshipApplicationStatus::RecommendedBySamiti->value, $application->status);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $application->id,
            'action' => 'recommend',
            'to_status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
        ]);
    }

    public function test_scholarship_routes_render_for_authorized_user(): void
    {
        $this->userWithPermissions();

        $this->get(route('applications.index'))->assertOk()->assertSee('Scholarship Applications');
        $this->get(route('workflow.index'))->assertOk()->assertSee('Scholarship Workflow');
        $this->get(route('reports.index'))->assertOk()->assertSee('Scholarship Reports');
    }

    public function test_csc_connect_callback_creates_vle_session(): void
    {
        Http::fake([
            config('csc.connect.token_endpoint') => Http::response(['access_token' => 'token-1']),
            config('csc.connect.resource_url') => Http::response([
                'User' => [
                    'csc_id' => '313676900017',
                    'fullname' => 'CSC Operator',
                    'email' => 'csc@example.test',
                    'mobile' => '9999999999',
                ],
            ]),
        ]);

        $this->withSession(['connect_state' => '12345'])
            ->get(route('csc.callback', ['code' => 'abc', 'state' => '12345']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'csc_id' => '313676900017',
            'user_type' => (int) config('csc.vle_role_id'),
        ]);
    }

    public function test_vle_submission_waits_for_wallet_success_before_final_submit(): void
    {
        $user = $this->userWithPermissions((int) config('csc.vle_role_id'), '313676900017');
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);

        $this->withSession(['USER_TYPE' => 'VLE', 'CSC_ID' => '313676900017'])
            ->post(route('applications.store'), $this->validPayload($session->id, 1) + ['intent' => 'submit'])
            ->assertRedirect();

        $application = ScholarshipApplication::query()->firstOrFail();
        $this->assertTrue($application->is_draft);
        $this->assertDatabaseHas('scholarship_wallet_transactions', [
            'scholarship_application_id' => $application->id,
            'transaction_type' => 'application_fee',
            'status' => 'pending',
            'amount' => 50.00,
        ]);

        $reference = $application->walletTransactions()->firstOrFail()->reference;

        $this->withSession(['USER_TYPE' => 'VLE', 'CSC_ID' => '313676900017'])
            ->get(route('applications.wallet.callback', [
                'application' => $application,
                'mock_success' => 1,
                'merchant_txn' => $reference,
            ]))
            ->assertRedirect(route('applications.show', $application));

        $submitted = $application->refresh();
        $this->assertFalse($submitted->is_draft);
        $this->assertNotNull($submitted->wallet_paid_at);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $application->id,
            'action' => 'wallet_payment_completed',
        ]);
    }

    private function service(): ScholarshipServiceInterface
    {
        return app(ScholarshipServiceInterface::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(int $sessionId, int $schemeId, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $sessionId,
            'scheme_id' => $schemeId,
            'student_aadhaar' => '111122223333',
            'student_name' => 'Asha Kumar',
            'class' => '10',
            'marks_obtained' => 400,
            'maximum_marks' => 500,
            'current_year_of_study' => 1,
            'student_bank_account_number' => '12345678901',
            'student_bank_ifsc' => 'SBIN0001234',
            'student_bank_name' => 'State Bank',
            'student_bank_branch' => 'Raipur',
            'student_bank_account_holder_name' => 'Asha Kumar',
            'head_of_family_aadhaar' => '999988887777',
            'head_of_family_name' => 'Family Head',
            'sangrahak_card_number' => 'SG-1',
            'tendupatta_collections' => [
                ['collection_year' => '2025-26', 'quantity_gaddi' => 12],
            ],
        ], $overrides);
    }

    private function userWithPermissions(int $roleId = 1, ?string $cscId = null): User
    {
        $user = User::factory()->create([
            'status' => '1',
            'user_type' => $roleId,
            'csc_id' => $cscId,
        ]);

        DB::table('role_priviledge')->insert([
            ['id' => 9101, 'role_id' => $roleId, 'permission_id' => 5],
            ['id' => 9102, 'role_id' => $roleId, 'permission_id' => 6],
            ['id' => 9103, 'role_id' => $roleId, 'permission_id' => 16],
        ]);

        $this->actingAs($user);

        return $user;
    }
}
