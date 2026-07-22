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
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationDocument;
use App\Models\User;
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

    public function test_scholarship_session_is_derived_from_application_date_and_filters_by_last_action(): void
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

        $application = $this->service()->createDraft($this->validPayload($currentSession->id, 1), $user);

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
            'scholarship_session_id' => $currentSession->id,
            'last_action_from_date' => '2026-07-20',
            'last_action_to_date' => '2026-07-20',
        ]))
            ->assertOk()
            ->assertSee('SCH-202627-000501')
            ->assertDontSee('SCH-202526-000502');
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
