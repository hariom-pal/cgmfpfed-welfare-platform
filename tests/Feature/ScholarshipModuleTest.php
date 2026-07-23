<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Domains\Scholarship\Enums\ApplicationState;
use App\Domains\Scholarship\Enums\PaymentState;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Domains\Scholarship\Enums\SubmissionState;
use App\Domains\Scholarship\Enums\WorkflowStage;
use App\Domains\Scholarship\Enums\WorkflowState;
use App\Models\AcademicSession;
use App\Models\DistrictUnion;
use App\Models\Phad;
use App\Models\Samiti;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationDocument;
use App\Models\User;
use App\Services\Export\ExportTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        $this->assertSame(ApplicationState::InWorkflow, $submitted->application_state);
        $this->assertSame(SubmissionState::Submitted, $submitted->submission_state);
        $this->assertSame(WorkflowState::PendingSamiti, $submitted->workflow_state);
        $this->assertSame(WorkflowStage::Samiti, $submitted->workflow_stage);
        $this->assertSame(PaymentState::WalletNotRequired, $submitted->payment_state);
        $this->assertSame('80.00', $submitted->percentage);
        $this->assertSame('2500.00', $submitted->amount);
        $this->assertNotNull($submitted->application_number);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $submitted->id,
            'action' => 'submitted',
        ]);
        $this->assertDatabaseHas('scholarship_workflow_transitions', [
            'scholarship_application_id' => $submitted->id,
            'action' => 'submitted',
            'to_workflow_state' => 'pending_samiti',
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
        $this->assertSame(WorkflowState::PendingIc, $application->workflow_state);
        $this->assertSame(WorkflowStage::Ic, $application->workflow_stage);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $application->id,
            'action' => 'recommend',
            'to_status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
        ]);
    }

    public function test_academic_session_is_derived_from_application_date_and_filters_by_last_action(): void
    {
        $user = $this->userWithPermissions();
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $currentSession = AcademicSession::factory()->create([
            'name' => '2026-27',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
        ]);
        $otherSession = AcademicSession::factory()->create([
            'name' => '2025-26',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
        ]);

        $application = $this->service()->createDraft($this->validPayload($otherSession->id, 1), $user);

        $this->assertSame($currentSession->id, $application->academic_session_id);
        $this->assertSame($currentSession->id, $application->scholarship_session_id);
        $this->assertSame('2026-27', $application->scholarship_session);

        $matched = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-202627-000501',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $currentSession->id,
            'scholarship_session_id' => $currentSession->id,
            'scholarship_session' => $currentSession->name,
            'student_name' => 'Matched Student',
            'student_aadhaar' => '444455556666',
        ]);
        $excluded = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-202526-000502',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $otherSession->id,
            'scholarship_session_id' => $otherSession->id,
            'scholarship_session' => $otherSession->name,
            'student_name' => 'Excluded Student',
            'student_aadhaar' => '777788889999',
        ]);

        DB::table('scholarship_workflow_transitions')->insert([
            [
                'scholarship_application_id' => $matched->id,
                'to_application_state' => 'in_workflow',
                'to_workflow_state' => 'pending_samiti',
                'to_workflow_stage' => 'samiti',
                'to_payment_state' => 'wallet_success',
                'to_approval_state' => 'pending',
                'action' => 'submitted',
                'acted_by' => $user->id,
                'acted_at' => '2026-07-20 10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scholarship_application_id' => $excluded->id,
                'to_application_state' => 'in_workflow',
                'to_workflow_state' => 'pending_samiti',
                'to_workflow_stage' => 'samiti',
                'to_payment_state' => 'wallet_success',
                'to_approval_state' => 'pending',
                'action' => 'submitted',
                'acted_by' => $user->id,
                'acted_at' => '2026-07-10 10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('applications.index', [
            'scheme' => $scheme->id,
            'academic_session_id' => $currentSession->id,
        ]))
            ->assertOk()
            ->assertSee('SCH-202627-000501')
            ->assertDontSee('SCH-202526-000502');

        $this->get(route('applications.index', [
            'scheme' => $scheme->id,
            'academic_session_id' => $currentSession->id,
            'last_action_from_date' => '2026-07-20',
            'last_action_to_date' => '2026-07-20',
        ]))
            ->assertOk()
            ->assertSee('SCH-202627-000501')
            ->assertDontSee('SCH-202526-000502');

        $this->get(route('reports.index', ['scheme' => $scheme->id, 'academic_session_id' => $currentSession->id]))
            ->assertOk()
            ->assertSee('SCH-202627-000501')
            ->assertDontSee('SCH-202526-000502');

        $this->get(route('workflow.index', ['academic_session_id' => $currentSession->id]))
            ->assertOk()
            ->assertSee('SCH-202627-000501')
            ->assertDontSee('SCH-202526-000502');
    }

    public function test_academic_session_master_is_reset_to_required_cycles(): void
    {
        $this->assertSame([
            ['2023-2024', '2023-08-01', '2024-07-31', 0],
            ['2024-2025', '2024-08-01', '2025-07-31', 0],
            ['2025-2026', '2025-08-01', '2026-07-31', 1],
        ], AcademicSession::query()
            ->orderBy('start_date')
            ->get(['name', 'start_date', 'end_date', 'is_active'])
            ->map(fn (AcademicSession $session): array => [
                $session->name,
                $session->start_date->toDateString(),
                $session->end_date->toDateString(),
                (int) $session->is_active,
            ])
            ->all());
    }

    public function test_application_listing_uses_required_filters_only(): void
    {
        $user = $this->userWithPermissions();
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::query()->where('name', '2025-2026')->firstOrFail();
        $otherSession = AcademicSession::query()->where('name', '2024-2025')->firstOrFail();
        $districtUnion = DistrictUnion::factory()->create(['name' => 'Union A']);
        $otherDistrictUnion = DistrictUnion::factory()->create(['name' => 'Union B']);
        $samiti = Samiti::factory()->create(['name' => 'Samiti A', 'district_union_id' => $districtUnion->id]);
        $otherSamiti = Samiti::factory()->create(['name' => 'Samiti B', 'district_union_id' => $otherDistrictUnion->id]);
        $phad = Phad::factory()->create(['name' => 'Phad A', 'district_union_id' => $districtUnion->id, 'samiti_id' => $samiti->id]);
        $otherPhad = Phad::factory()->create(['name' => 'Phad B', 'district_union_id' => $otherDistrictUnion->id, 'samiti_id' => $otherSamiti->id]);

        $matched = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-MATCH-001',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'district_union_id' => $districtUnion->id,
            'samiti_id' => $samiti->id,
            'phad_id' => $phad->id,
            'student_name' => 'Filter Match Student',
            'student_aadhaar' => '123456789012',
        ]);
        $excluded = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-OTHER-001',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $otherSession->id,
            'district_union_id' => $otherDistrictUnion->id,
            'samiti_id' => $otherSamiti->id,
            'phad_id' => $otherPhad->id,
            'student_name' => 'Another Student',
            'student_aadhaar' => '999999999999',
        ]);

        DB::table('scholarship_workflow_transitions')->insert([
            [
                'scholarship_application_id' => $matched->id,
                'to_application_state' => 'in_workflow',
                'to_workflow_state' => 'pending_samiti',
                'to_workflow_stage' => 'samiti',
                'to_payment_state' => 'wallet_success',
                'to_approval_state' => 'pending',
                'action' => 'recommend',
                'acted_by' => $user->id,
                'acted_by_role' => 'Samiti',
                'acted_at' => '2026-07-20 10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scholarship_application_id' => $excluded->id,
                'to_application_state' => 'in_workflow',
                'to_workflow_state' => 'pending_samiti',
                'to_workflow_stage' => 'samiti',
                'to_payment_state' => 'wallet_success',
                'to_approval_state' => 'pending',
                'action' => 'recommend',
                'acted_by' => $user->id,
                'acted_by_role' => 'VLE',
                'acted_at' => '2026-07-21 10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('applications.index', [
            'scheme' => $scheme->id,
            'academic_session_id' => $session->id,
            'district_union_id' => $districtUnion->id,
            'samiti_id' => $samiti->id,
            'phad_id' => $phad->id,
            'application_number' => 'MATCH',
            'aadhaar_number' => '123456789012',
            'student_name' => 'Filter Match',
            'last_action_from_date' => '2026-07-20',
            'last_action_to_date' => '2026-07-20',
            'last_action_role' => 'Samiti',
            'status' => ScholarshipApplicationStatus::PaymentCompleted->value,
            'q' => 'Another',
        ]))
            ->assertOk()
            ->assertSee('SCH-MATCH-001')
            ->assertDontSee('SCH-OTHER-001')
            ->assertDontSee('Current Status')
            ->assertDontSee('Current Workflow Level');
    }

    public function test_application_status_menu_filters_show_respective_lists(): void
    {
        $vle = $this->userWithPermissions((int) config('csc.vle_role_id'));
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        $pendingAtVle = ScholarshipApplication::factory()->create([
            'application_number' => 'SCH-STATUS-VLE',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
        ]);
        $submittedPendingSamiti = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-STATUS-SAMITI',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::Pending->value,
        ]);
        $underProcess = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-STATUS-PROCESS',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
        ]);
        $rejected = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-STATUS-REJECTED',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RejectedBySamiti->value,
        ]);
        $completed = ScholarshipApplication::factory()->completed()->create([
            'application_number' => 'SCH-STATUS-COMPLETED',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
        ]);

        // "Pending at VLE" must only show applications never submitted (still with the VLE),
        // not applications already submitted and awaiting Samiti verification.
        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'pending_vle']))
            ->assertOk()
            ->assertSee($pendingAtVle->application_number)
            ->assertDontSee($submittedPendingSamiti->application_number)
            ->assertDontSee($underProcess->application_number)
            ->assertDontSee($rejected->application_number)
            ->assertDontSee($completed->application_number);

        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'pending']))
            ->assertOk()
            ->assertSee($underProcess->application_number)
            ->assertDontSee($pendingAtVle->application_number)
            ->assertDontSee($rejected->application_number)
            ->assertDontSee($completed->application_number);

        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'rejected']))
            ->assertOk()
            ->assertSee($rejected->application_number)
            ->assertDontSee($pendingAtVle->application_number)
            ->assertDontSee($underProcess->application_number)
            ->assertDontSee($completed->application_number);

        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'completed']))
            ->assertOk()
            ->assertSee($completed->application_number)
            ->assertDontSee($pendingAtVle->application_number)
            ->assertDontSee($underProcess->application_number)
            ->assertDontSee($rejected->application_number);

        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id]))
            ->assertOk()
            ->assertSee($pendingAtVle->application_number)
            ->assertSee($underProcess->application_number)
            ->assertSee($rejected->application_number)
            ->assertSee($completed->application_number);
    }

    public function test_payment_failed_menu_shows_only_payment_failed_applications(): void
    {
        $vle = $this->userWithPermissions((int) config('csc.vle_role_id'));
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        $paymentFailed = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-PF-FAILED',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::PaymentFailed->value,
        ]);
        $paymentFailedViaCcf = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-PF-FAILED-CCF',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::PaymentFailedViaCCF->value,
        ]);
        $completed = ScholarshipApplication::factory()->completed()->create([
            'application_number' => 'SCH-PF-COMPLETED',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
        ]);

        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'payment_failed']))
            ->assertOk()
            ->assertSee($paymentFailed->application_number)
            ->assertSee($paymentFailedViaCcf->application_number)
            ->assertDontSee($completed->application_number);
    }

    public function test_academic_session_defaults_to_active_session_and_can_be_switched(): void
    {
        $this->userWithPermissions();
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $activeSession = AcademicSession::factory()->create([
            'name' => '2026-27',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_active' => true,
        ]);
        $otherSession = AcademicSession::factory()->create([
            'name' => '2025-26',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => false,
        ]);

        $inActiveSession = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-SESSION-ACTIVE',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $activeSession->id,
        ]);
        $inOtherSession = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-SESSION-OTHER',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $otherSession->id,
        ]);

        // No academic_session_id supplied — must default to the active session.
        $this->get(route('applications.index', ['scheme' => $scheme->id]))
            ->assertOk()
            ->assertSee($inActiveSession->application_number)
            ->assertDontSee($inOtherSession->application_number);

        // Explicitly switching the session reloads the list for that session.
        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $otherSession->id]))
            ->assertOk()
            ->assertSee($inOtherSession->application_number)
            ->assertDontSee($inActiveSession->application_number);
    }

    public function test_dependent_dropdowns_start_empty_until_parent_selected(): void
    {
        $this->userWithPermissions((int) config('csc.vle_role_id'));
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $districtUnion = DistrictUnion::factory()->create(['name' => 'Union Alpha']);
        $samiti = Samiti::factory()->create(['name' => 'Samiti Alpha', 'district_union_id' => $districtUnion->id]);
        Phad::factory()->create(['name' => 'Phad Alpha', 'district_union_id' => $districtUnion->id, 'samiti_id' => $samiti->id]);

        $this->get(route('applications.index', ['scheme' => $scheme->id]))
            ->assertOk()
            ->assertSee('Union Alpha')
            ->assertDontSee('Samiti Alpha')
            ->assertDontSee('Phad Alpha');

        $this->get(route('applications.index', ['scheme' => $scheme->id, 'district_union_id' => $districtUnion->id]))
            ->assertOk()
            ->assertSee('Samiti Alpha')
            ->assertDontSee('Phad Alpha');

        $formHtml = $this->get(route('applications.create.scheme', $scheme))->assertOk()->getContent();
        $this->assertStringContainsString('Union Alpha', $formHtml);
        $this->assertStringNotContainsString('Samiti Alpha', $formHtml);
        $this->assertStringNotContainsString('Phad Alpha', $formHtml);
    }

    public function test_scholarship_payment_attempts_are_independent_from_wallet_payment(): void
    {
        $user = $this->userWithPermissions();
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);

        $application = $this->service()->createDraft($this->validPayload($session->id, 1), $user);
        $application = $this->service()->submit($application, $user);
        $application = $this->service()->transition($application, 'recommend', 'Samiti verified', $user);
        $application = $this->service()->transition($application, 'recommend', 'IC verified', $user);
        $application = $this->service()->transition($application, 'recommend', 'DU verified', $user);
        $application = $this->service()->transition($application, 'recommend', 'HQ recommended', $user);
        $application = $this->service()->transition($application, 'recommend', 'Accounts finalized', $user);

        $batch = $this->service()->createPaymentBatch([$application->id], $user, 'Payment file submitted');

        $this->assertDatabaseHas('scholarship_payment_attempts', [
            'scholarship_application_id' => $application->id,
            'payment_purpose' => 'scholarship_disbursement',
            'payment_channel' => 'axis_bank',
            'transaction_number' => $batch->batch_number,
            'payment_state' => 'submitted',
            'wallet_transaction_id' => null,
        ]);

        $paid = $this->service()->recordPaymentResult($application->refresh(), true, 'AXIS-UTR-1', null, $user);

        $this->assertSame(ScholarshipApplicationStatus::PaymentCompleted->value, $paid->status);
        $this->assertSame('AXIS-UTR-1', $paid->payment_reference_id);
        $this->assertDatabaseHas('scholarship_payment_attempts', [
            'scholarship_application_id' => $application->id,
            'payment_purpose' => 'scholarship_disbursement',
            'payment_channel' => 'axis_bank',
            'transaction_number' => 'AXIS-UTR-1',
            'payment_state' => 'completed',
            'wallet_transaction_id' => null,
        ]);
        $this->assertDatabaseMissing('scholarship_wallet_transactions', [
            'reference' => 'AXIS-UTR-1',
        ]);
    }

    public function test_application_list_pagination_preserves_filters_and_renders_bootstrap_links(): void
    {
        $this->userWithPermissions();
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        ScholarshipApplication::factory()->count(25)->submitted()->create([
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
        ]);

        $pageOne = $this->get(route('applications.index', [
            'scheme' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => 'pending',
        ]));
        $pageOne->assertOk();
        $pageOne->assertSee('page=2', false);
        $pageOne->assertSee('page-link', false);
        $pageOne->assertDontSee('bg-white dark:bg-gray-800', false);

        $pageTwo = $this->get(route('applications.index', [
            'scheme' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => 'pending',
            'page' => 2,
        ]));
        $pageTwo->assertOk();
        $pageTwo->assertSee('Showing 21 to 25 of 25 records');
    }

    public function test_pending_at_vle_uses_authoritative_status_zero_and_wallet_unpaid_rule(): void
    {
        // Authoritative business rule (legacy `application` table): application_status = 0
        // AND payment_txn_status = 0. `status` is the direct 1:1 copy of legacy
        // application_status; `wallet_paid_at` is only ever set when the wallet fee payment
        // succeeds (payment_txn_status = 1), so `wallet_paid_at IS NULL` is the exact
        // equivalent of payment_txn_status = 0. This must hold independent of
        // application_state/workflow_state (which the legacy-redesign migration backfills
        // unreliably for legacy-imported rows).
        $vle = $this->userWithPermissions((int) config('csc.vle_role_id'));
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        $legacyDraft = ScholarshipApplication::factory()->create([
            'application_number' => 'SCH-LEGACY-DRAFT',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::Pending->value,
            'wallet_paid_at' => null,
            'application_state' => ApplicationState::InWorkflow->value,
            'workflow_state' => WorkflowState::PendingSamiti->value,
            'is_draft' => false,
        ]);

        $walletPaidButStatusZero = ScholarshipApplication::factory()->create([
            'application_number' => 'SCH-WALLET-PAID',
            'applicant_user_id' => $vle->id,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::Pending->value,
            'wallet_paid_at' => now(),
        ]);

        $response = $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'pending_vle']));
        $response->assertOk()
            ->assertSee($legacyDraft->application_number)
            ->assertDontSee($walletPaidButStatusZero->application_number);
    }

    public function test_pending_at_vle_visibility_follows_organizational_hierarchy(): void
    {
        // Real migrated data: applicant_user_id is null for ~99.97% of applications because
        // VLE users are only JIT-provisioned on first CSC login (see MIGRATION_AUDIT.md).
        // Samiti/District Union/Super Admin must still be able to see and act on these
        // unclaimed drafts, scoped by the same samiti_id/district_union_id used for every
        // other application state — applicant_user_id must never be touched to achieve this.
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        $districtUnion = DistrictUnion::factory()->create();
        $samiti = Samiti::factory()->create(['district_union_id' => $districtUnion->id]);

        $unclaimedDraft = ScholarshipApplication::factory()->create([
            'application_number' => 'SCH-HIER-DRAFT',
            'applicant_user_id' => null,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'district_union_id' => $districtUnion->id,
            'samiti_id' => $samiti->id,
        ]);

        $otherDraft = ScholarshipApplication::factory()->create([
            'application_number' => 'SCH-HIER-OTHER',
            'applicant_user_id' => null,
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'district_union_id' => DistrictUnion::factory()->create()->id,
            'samiti_id' => Samiti::factory()->create()->id,
        ]);

        $samitiUser = $this->userWithPermissions(3);
        $samitiUser->forceFill(['samiti' => $samiti->id, 'districtunion' => $districtUnion->id])->save();
        $this->actingAs($samitiUser);
        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'pending_vle']))
            ->assertOk()
            ->assertSee($unclaimedDraft->application_number)
            ->assertDontSee($otherDraft->application_number);

        $duUser = $this->userWithPermissions(2);
        $duUser->forceFill(['districtunion' => $districtUnion->id])->save();
        $this->actingAs($duUser);
        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'pending_vle']))
            ->assertOk()
            ->assertSee($unclaimedDraft->application_number)
            ->assertDontSee($otherDraft->application_number);

        $this->userWithPermissions(1);
        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'pending_vle']))
            ->assertOk()
            ->assertSee($unclaimedDraft->application_number)
            ->assertSee($otherDraft->application_number);

        $this->assertNull($unclaimedDraft->refresh()->applicant_user_id);
    }

    public function test_last_action_role_filter_falls_back_to_legacy_audit_history(): void
    {
        // `CompleteLegacyDataMigrationSeeder::migrateAudits()` only writes
        // `scholarship_application_audits`, never `scholarship_workflow_transitions` — so for
        // every legacy-imported application the transitions table is empty and the filter must
        // fall back to the audit trail's actor role.
        $admin = $this->userWithPermissions(1);
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        $samitiUser = $this->userWithPermissions(3);
        $this->actingAs($admin);

        $legacyApplication = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-AUDIT-ONLY',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
        ]);
        DB::table('scholarship_application_audits')->insert([
            'scholarship_application_id' => $legacyApplication->id,
            'from_status' => 0,
            'to_status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
            'action' => 'legacy_status_migrated',
            'stage' => 'samiti',
            'acted_by' => $samitiUser->id,
            'acted_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherApplication = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-AUDIT-OTHER',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RecommendedByIC->value,
        ]);

        $this->get(route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'last_action_role' => 'Samiti']))
            ->assertOk()
            ->assertSee($legacyApplication->application_number)
            ->assertDontSee($otherApplication->application_number);
    }

    public function test_submitted_by_always_shows_csc_id_and_resolves_linked_user_when_available(): void
    {
        $this->userWithPermissions(1);
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        $unlinked = ScholarshipApplication::factory()->create([
            'application_number' => 'SCH-SUBMITTER-UNLINKED',
            'applicant_user_id' => null,
            'legacy_added_by' => '999888777001',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
        ]);

        $this->get(route('applications.show', $unlinked))
            ->assertOk()
            ->assertSee('999888777001')
            ->assertSee('Not yet linked to a portal account');

        User::factory()->create([
            'user_type' => (int) config('csc.vle_role_id'),
            'csc_id' => '999888777002',
            'name' => 'Ramesh VLE',
        ]);
        $linked = ScholarshipApplication::factory()->create([
            'application_number' => 'SCH-SUBMITTER-LINKED',
            'applicant_user_id' => null,
            'legacy_added_by' => '999888777002',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
        ]);

        $this->get(route('applications.show', $linked))
            ->assertOk()
            ->assertSee('999888777002')
            ->assertSee('Ramesh VLE');
    }

    public function test_audit_trail_shows_actor_role_district_union_and_samiti(): void
    {
        $this->userWithPermissions(1);
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        $districtUnion = DistrictUnion::factory()->create(['name' => 'Raipur District Union']);
        $samiti = Samiti::factory()->create(['name' => 'ABC Samiti', 'district_union_id' => $districtUnion->id]);

        $samitiUser = User::factory()->create([
            'user_type' => 3,
            'name' => 'Mukesh Verma',
            'district_union_master_id' => $districtUnion->id,
            'samiti_master_id' => $samiti->id,
        ]);

        $application = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-AUDIT-DISPLAY',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RejectedBySamiti->value,
        ]);

        DB::table('scholarship_workflow_transitions')->insert([
            'scholarship_application_id' => $application->id,
            'from_application_state' => 'in_workflow',
            'to_application_state' => 'returned_for_correction',
            'from_workflow_state' => 'pending_samiti',
            'to_workflow_state' => 'returned_for_correction',
            'from_workflow_stage' => 'samiti',
            'to_workflow_stage' => 'samiti',
            'from_payment_state' => 'wallet_not_required',
            'to_payment_state' => 'wallet_not_required',
            'from_approval_state' => 'pending',
            'to_approval_state' => 'returned_for_correction',
            'action' => 'return',
            'remarks' => 'Missing documents',
            'acted_by' => $samitiUser->id,
            'acted_by_role' => 'Samiti',
            'acted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Mukesh Verma')
            ->assertSee('Samiti')
            ->assertSee('Raipur District Union')
            ->assertSee('ABC Samiti')
            ->assertSee('Missing documents');
    }

    public function test_dashboard_cards_link_to_correctly_filtered_application_lists(): void
    {
        $this->userWithPermissions(1);
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        $rejected = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-DASH-REJECTED',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RejectedBySamiti->value,
        ]);

        $response = $this->get(route('dashboard', ['scheme' => $scheme->id, 'academic_session_id' => $session->id]));
        $response->assertOk();

        $expectedHref = route('applications.index', ['scheme' => $scheme->id, 'academic_session_id' => $session->id, 'status' => 'rejected']);
        $response->assertSee(htmlspecialchars($expectedHref), false);

        $this->get($expectedHref)->assertOk()->assertSee($rejected->application_number);
    }

    public function test_csv_export_streams_only_filtered_applications(): void
    {
        $this->userWithPermissions(1);
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        $included = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-CSV-INCLUDED',
            'student_name' => 'Included Student',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
        ]);
        $excluded = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-CSV-EXCLUDED',
            'student_name' => 'Excluded Student',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::PaymentCompleted->value,
        ]);

        $response = $this->get(route('applications.export', [
            'scheme' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => 'pending',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Application Number', $csv);
        $this->assertStringContainsString($included->application_number, $csv);
        $this->assertStringNotContainsString($excluded->application_number, $csv);
    }

    public function test_csv_export_includes_vle_and_audit_mandatory_fields(): void
    {
        $this->userWithPermissions(1);
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        $application = ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-CSV-AUDIT-FIELDS',
            'applicant_user_id' => null,
            'legacy_added_by' => '888777666555',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
        ]);

        DB::table('scholarship_workflow_transitions')->insert([
            'scholarship_application_id' => $application->id,
            'from_application_state' => 'in_workflow',
            'to_application_state' => 'in_workflow',
            'from_workflow_state' => 'pending_samiti',
            'to_workflow_state' => 'pending_ic',
            'from_workflow_stage' => 'samiti',
            'to_workflow_stage' => 'ic',
            'from_payment_state' => 'wallet_not_required',
            'to_payment_state' => 'wallet_not_required',
            'from_approval_state' => 'pending',
            'to_approval_state' => 'recommended',
            'action' => 'recommend',
            'remarks' => 'Looks good',
            'acted_by_role' => 'Samiti',
            'acted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('applications.export', [
            'scheme' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => 'pending',
        ]));

        $csv = $response->streamedContent();
        $header = strtok($csv, "\n");
        $this->assertIsString($header);

        $this->assertStringContainsString('Added By (CSC ID)', $header);
        $this->assertStringContainsString('Current Stage', $header);
        $this->assertStringNotContainsString('Legacy', $header);
        $this->assertStringNotContainsString('VLE Name', $header);
        $this->assertStringNotContainsString('Linked Laravel User', $header);
        $this->assertStringContainsString('888777666555', $csv);
        $this->assertStringContainsString('Samiti', $csv);
        $this->assertStringContainsString('Looks good', $csv);
    }

    public function test_csv_export_configuration_can_hide_and_reorder_columns_and_export_reflects_it(): void
    {
        $this->userWithPermissions(1);
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        $session = AcademicSession::factory()->create(['name' => '2026-27']);

        ScholarshipApplication::factory()->submitted()->create([
            'application_number' => 'SCH-CFG-1',
            'scheme_id' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
        ]);

        $this->get(route('settings.csv-export-configuration.index'))->assertOk()->assertSee('Scholarship Applications');
        $this->get(route('settings.csv-export-configuration.edit', 'scholarship_applications'))->assertOk()->assertSee('application_number');

        $fields = app(ExportTemplateService::class)->fieldsFor('scholarship_applications');
        $payload = [];
        foreach ($fields as $field) {
            $entry = [
                'field_name' => $field['field_name'],
                'display_name' => $field['display_name'],
            ];
            if ($field['field_name'] !== 'mobile') {
                $entry['is_visible'] = '1';
            }
            $payload[] = $entry;
        }

        $this->put(route('settings.csv-export-configuration.update', 'scholarship_applications'), ['fields' => $payload])
            ->assertRedirect(route('settings.csv-export-configuration.edit', 'scholarship_applications'));

        $response = $this->get(route('applications.export', [
            'scheme' => $scheme->id,
            'academic_session_id' => $session->id,
            'status' => 'pending',
        ]));

        $header = strtok($response->streamedContent(), "\n");
        $this->assertIsString($header);
        $this->assertStringNotContainsString('Mobile', $header);
        $this->assertStringContainsString('Application Number', $header);
    }

    public function test_lifecycle_factories_use_normalized_enums(): void
    {
        $application = ScholarshipApplication::factory()->completed()->create();

        $this->assertSame(ApplicationState::Completed, $application->application_state);
        $this->assertSame(WorkflowState::PaymentCompleted, $application->workflow_state);
        $this->assertSame(PaymentState::BeneficiaryPaymentSuccess, $application->payment_state);
        $this->assertNotNull($application->completed_at);
    }

    public function test_scholarship_routes_render_for_authorized_user(): void
    {
        $this->userWithPermissions();

        $this->get(route('applications.index'))->assertOk()->assertSee('Select a Scheme to view applications');
        $this->get(route('workflow.index'))->assertOk()->assertSee('Scholarship Workflow');
        $this->get(route('reports.index'))->assertOk()->assertSee('Scholarship Reports');
    }

    public function test_vle_application_form_contains_production_sections(): void
    {
        $this->userWithPermissions((int) config('csc.vle_role_id'));
        AcademicSession::factory()->create(['name' => '2026-27']);
        $scheme = Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Meritorious Student Award Scheme']);

        $this->get(route('applications.create'))
            ->assertOk()
            ->assertSee('Select a Scheme to continue to add application', false)
            ->assertSee(route('applications.create.scheme', $scheme), false);

        $this->get(route('applications.create.scheme', $scheme))
            ->assertOk()
            ->assertSee('Information Regarding Primary Society', false)
            ->assertSee('Head of Family Detail', false)
            ->assertSee('Information of Student', false)
            ->assertSee('Student Educational Detail', false)
            ->assertSee('Tendupatta Collection Details', false)
            ->assertSee('Student Bank Details', false)
            ->assertSee('Attach Document', false)
            ->assertSee('document_uploads[tpcard]', false)
            ->assertSee('student_bank_account_number', false);
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
        $this->assertSame(ApplicationState::Created, $application->application_state);
        $this->assertSame(SubmissionState::WalletPending, $application->fresh()->submission_state);
        $this->assertSame(PaymentState::WalletPending, $application->fresh()->payment_state);
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
        $this->assertSame(ApplicationState::InWorkflow, $submitted->application_state);
        $this->assertSame(SubmissionState::Submitted, $submitted->submission_state);
        $this->assertSame(WorkflowState::PendingSamiti, $submitted->workflow_state);
        $this->assertSame(PaymentState::WalletSuccess, $submitted->payment_state);
        $this->assertDatabaseHas('scholarship_payment_attempts', [
            'scholarship_application_id' => $application->id,
            'transaction_number' => $reference,
            'payment_state' => 'completed',
        ]);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $application->id,
            'action' => 'wallet_payment_completed',
        ]);
    }

    public function test_document_uploads_keep_metadata_and_version_history(): void
    {
        $user = $this->userWithPermissions();
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);

        $application = $this->service()->createDraft($this->validPayload($session->id, 1, [
            'documents' => [
                'tpcard' => [
                    'file_path' => 'scholarship-documents/first.pdf',
                    'storage_disk' => 'public',
                    'original_file_name' => 'first.pdf',
                    'stored_file_name' => 'first.pdf',
                    'file_extension' => 'pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 1024,
                ],
                'haadharcard' => ['file_path' => 'tests/hof-aadhaar.pdf'],
                'aadharcard' => ['file_path' => 'tests/student-aadhaar.pdf'],
                'admission_copy' => ['file_path' => 'tests/marksheet.pdf'],
                'passbook' => ['file_path' => 'tests/passbook.pdf'],
            ],
        ]), $user);

        $application = $this->service()->updateDraft($application, $this->validPayload($session->id, 1, [
            'documents' => [
                'tpcard' => [
                    'file_path' => 'scholarship-documents/second.pdf',
                    'original_file_name' => 'second.pdf',
                    'stored_file_name' => 'second.pdf',
                    'file_extension' => 'pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 2048,
                ],
            ],
        ]), $user);

        $documents = ScholarshipApplicationDocument::query()
            ->where('scholarship_application_id', $application->id)
            ->where('document_type', 'tpcard')
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $documents);
        $this->assertFalse($documents[0]->is_current);
        $this->assertTrue($documents[1]->is_current);
        $this->assertSame(2, $documents[1]->version);
        $this->assertSame($documents[0]->id, $documents[1]->previous_document_id);
        $this->assertSame('111122223333', $documents[1]->student_identifier);
        $this->assertSame(1, $documents[1]->scheme_id);
        $this->assertSame('second.pdf', $documents[1]->original_file_name);
        $this->assertSame('application/pdf', $documents[1]->mime_type);
        $this->assertSame(2048, $documents[1]->file_size);
        $this->assertSame($user->id, $documents[1]->uploaded_by);
    }

    public function test_document_is_served_inline_through_authorized_controller(): void
    {
        Storage::fake('public');

        $user = $this->userWithPermissions();
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        Storage::disk('public')->put('scholarship-documents/student.pdf', '%PDF-1.4');

        $application = $this->service()->createDraft($this->validPayload($session->id, 1, [
            'documents' => [
                'tpcard' => [
                    'file_path' => 'scholarship-documents/student.pdf',
                    'original_file_name' => 'student.pdf',
                    'stored_file_name' => 'student.pdf',
                    'file_extension' => 'pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 8,
                ],
                'haadharcard' => ['file_path' => 'tests/hof-aadhaar.pdf'],
                'aadharcard' => ['file_path' => 'tests/student-aadhaar.pdf'],
                'admission_copy' => ['file_path' => 'tests/marksheet.pdf'],
                'passbook' => ['file_path' => 'tests/passbook.pdf'],
            ],
        ]), $user);
        $application = $this->service()->submit($application, $user);

        $document = $application->currentDocuments()->where('document_type', 'tpcard')->firstOrFail();

        $this->get(route('applications.documents.show', [$application, $document]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('applications.documents.download', [$application, $document]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=student.pdf');
    }

    public function test_vle_cannot_access_documents_for_other_vle_applications(): void
    {
        Storage::fake('public');

        $owner = $this->userWithPermissions((int) config('csc.vle_role_id'), '313676900017');
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);
        Storage::disk('public')->put('scholarship-documents/student.pdf', '%PDF-1.4');

        $application = $this->service()->createDraft($this->validPayload($session->id, 1, [
            'documents' => [
                'tpcard' => [
                    'file_path' => 'scholarship-documents/student.pdf',
                    'original_file_name' => 'student.pdf',
                    'stored_file_name' => 'student.pdf',
                    'file_extension' => 'pdf',
                    'mime_type' => 'application/pdf',
                ],
                'haadharcard' => ['file_path' => 'tests/hof-aadhaar.pdf'],
                'aadharcard' => ['file_path' => 'tests/student-aadhaar.pdf'],
                'admission_copy' => ['file_path' => 'tests/marksheet.pdf'],
                'passbook' => ['file_path' => 'tests/passbook.pdf'],
            ],
        ]), $owner);
        $document = $application->currentDocuments()->where('document_type', 'tpcard')->firstOrFail();

        $other = $this->userWithPermissions((int) config('csc.vle_role_id'), '313676900018');
        $this->actingAs($other);

        $this->get(route('applications.documents.show', [$application, $document]))->assertNotFound();
    }

    public function test_return_for_correction_locks_unselected_documents(): void
    {
        $user = $this->userWithPermissions();
        $session = AcademicSession::factory()->create(['name' => '2026-27']);
        Scheme::factory()->create(['id' => 1, 'code' => 'SCH1', 'name' => 'Class Scholarship']);

        $application = $this->service()->createDraft($this->validPayload($session->id, 1), $user);
        $application = $this->service()->submit($application, $user);
        $application = $this->service()->transition($application, 'return', 'Replace passbook only', $user, ['supporting_documents'], ['passbook']);

        $this->assertSame(['passbook'], $application->metadata['editable_documents']);
        $this->assertTrue((bool) $application->currentDocuments()->where('document_type', 'passbook')->value('editable_after_return'));
        $this->assertFalse((bool) $application->currentDocuments()->where('document_type', 'tpcard')->value('editable_after_return'));

        $this->expectException(ValidationException::class);
        $this->service()->resubmit($application, $this->validPayload($session->id, 1, [
            'documents' => ['tpcard' => ['file_path' => 'tests/replaced.pdf']],
        ]), $user);
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
            'gender' => 'Female',
            'date_of_birth' => '2010-01-01',
            'mobile' => '9876543210',
            'address' => 'Forest Colony',
            'pincode' => '492001',
            'district_id' => 1,
            'district_union_id' => 1,
            'samiti_id' => 1,
            'phad_id' => 1,
            'block_code' => '101',
            'area' => 'Rural',
            'gram_panchayat_code' => '101001',
            'village_code' => '101001001',
            'class' => '10',
            'school_college_name' => 'Government School',
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
            'documents' => [
                'tpcard' => ['file_path' => 'tests/tpcard.pdf'],
                'haadharcard' => ['file_path' => 'tests/hof-aadhaar.pdf'],
                'aadharcard' => ['file_path' => 'tests/student-aadhaar.pdf'],
                'admission_copy' => ['file_path' => 'tests/marksheet.pdf'],
                'passbook' => ['file_path' => 'tests/passbook.pdf'],
            ],
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

        $nextId = ((int) DB::table('role_priviledge')->max('id')) + 1;
        foreach ([5, 6, 16] as $offset => $permissionId) {
            DB::table('role_priviledge')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['id' => $nextId + $offset],
            );
        }

        $this->actingAs($user);

        return $user;
    }
}
