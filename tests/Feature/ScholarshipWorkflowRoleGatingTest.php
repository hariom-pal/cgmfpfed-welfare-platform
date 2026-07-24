<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\AcademicSession;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ScholarshipWorkflowRoleGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_samiti_may_recommend_a_pending_application(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $application = $this->application($session->id, $scheme->id, ['status' => ScholarshipApplicationStatus::Pending->value]);

        $ic = $this->legacyUser(4, ['districtunion' => 1]);
        $this->actingAs($ic);
        $this->post(route('workflow.action', $application), ['action' => 'recommend'])->assertForbidden();

        $samiti = $this->legacyUser(3, ['districtunion' => 1, 'samiti' => 1]);
        $this->actingAs($samiti);
        $this->post(route('workflow.action', $application), ['action' => 'recommend'])->assertRedirect();

        $this->assertSame(ScholarshipApplicationStatus::RecommendedBySamiti->value, $application->refresh()->status);
    }

    public function test_only_district_union_may_act_on_ic_recommended_application(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::RecommendedByIC->value,
            'workflow_stage' => 'district_union',
            'district_union_id' => 1,
        ]);

        $ic = $this->legacyUser(4, ['districtunion' => 1]);
        $this->actingAs($ic);
        $this->post(route('workflow.action', $application), ['action' => 'recommend'])->assertForbidden();

        $du = $this->legacyUser(2, ['districtunion' => 1]);
        $this->actingAs($du);
        $this->post(route('workflow.action', $application), ['action' => 'recommend'])->assertRedirect();

        $this->assertSame(ScholarshipApplicationStatus::RecommendedByDistrictUnion->value, $application->refresh()->status);
    }

    public function test_only_account_may_forward_or_remove_recommended_for_payment(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::RecommendedForPayment->value,
            'workflow_stage' => 'accounts',
        ]);

        $admin = $this->legacyUser(1);
        $this->actingAs($admin);
        $this->post(route('workflow.action', $application), ['action' => 'forward'])->assertForbidden();

        $account = $this->legacyUser(6);
        $this->actingAs($account);
        $this->post(route('workflow.action', $application), ['action' => 'forward'])->assertRedirect();
        $this->assertSame(ScholarshipApplicationStatus::FinalApplicationForPayment->value, $application->refresh()->status);

        $this->actingAs($admin);
        $this->post(route('workflow.action', $application), ['action' => 'remove'])->assertForbidden();

        $this->actingAs($account);
        $this->post(route('workflow.action', $application), ['action' => 'remove'])->assertRedirect();
        $this->assertSame(ScholarshipApplicationStatus::RecommendedForPayment->value, $application->refresh()->status);
    }

    public function test_payment_failed_can_only_be_retried_by_account_or_super_admin(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::PaymentFailed->value,
            'workflow_stage' => 'accounts',
        ]);

        $du = $this->legacyUser(2, ['districtunion' => 1]);
        $this->actingAs($du);
        $this->post(route('workflow.action', $application), ['action' => 'retry'])->assertForbidden();

        $account = $this->legacyUser(6);
        $this->actingAs($account);
        $this->post(route('workflow.action', $application), ['action' => 'retry'])->assertRedirect();

        $this->assertSame(ScholarshipApplicationStatus::RecommendedForPayment->value, $application->refresh()->status);
    }

    public function test_account_details_updated_by_hq_can_only_be_recommended_by_super_admin(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::AccountDetailsUpdatedByHQ->value,
            'workflow_stage' => 'hq',
            'district_union_id' => 1,
        ]);

        $du = $this->legacyUser(2, ['districtunion' => 1]);
        $this->actingAs($du);
        $this->post(route('workflow.action', $application), ['action' => 'recommend'])->assertForbidden();

        $admin = $this->legacyUser(1);
        $this->actingAs($admin);
        $this->post(route('workflow.action', $application), ['action' => 'recommend'])->assertRedirect();

        $this->assertSame(ScholarshipApplicationStatus::RecommendedForPayment->value, $application->refresh()->status);
    }

    public function test_payment_result_can_only_be_recorded_by_super_admin_on_a_submitted_batch(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::PaymentBatchSubmitted->value,
            'workflow_stage' => 'accounts',
        ]);

        $account = $this->legacyUser(6);
        $this->actingAs($account);
        $this->post(route('workflow.payment-result', $application), ['success' => '1'])->assertForbidden();

        $admin = $this->legacyUser(1);
        $this->actingAs($admin);
        $this->post(route('workflow.payment-result', $application), ['success' => '1', 'payment_reference_id' => 'UTR-1'])->assertRedirect();

        $this->assertSame(ScholarshipApplicationStatus::PaymentCompleted->value, $application->refresh()->status);
    }

    public function test_only_ic_role_can_submit_an_ic_batch(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
            'district_union_id' => 1,
        ]);

        $admin = $this->legacyUser(1);
        $this->actingAs($admin);
        $this->post(route('workflow.ic-batches.store'), [
            'application_ids' => [$application->id],
            'mom_file_path' => 'uploads/mom/1.pdf',
        ])->assertForbidden();

        $ic = $this->legacyUser(4, ['districtunion' => 1]);
        $this->actingAs($ic);
        $this->post(route('workflow.ic-batches.store'), [
            'application_ids' => [$application->id],
            'mom_file_path' => 'uploads/mom/1.pdf',
        ])->assertRedirect();

        // Batch creation only records the MoM batch — the IC's own recommend/return/reject
        // decision on each application is a separate, subsequent action (§10.1).
        $this->assertSame(ScholarshipApplicationStatus::RecommendedBySamiti->value, $application->refresh()->status);
        $this->assertDatabaseHas('scholarship_batch_applications', ['scholarship_application_id' => $application->id]);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $application->id,
            'action' => 'ic_batch_submitted',
        ]);
    }

    public function test_ic_can_modify_amount_only_to_a_scheme_fixed_value_and_it_is_audited(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create(['id' => 1]);
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
            'district_union_id' => 1,
            'amount' => 2500,
        ]);

        $ic = $this->legacyUser(4, ['districtunion' => 1]);
        $this->actingAs($ic);

        // Not one of scheme 1's fixed amounts (2500, 3000) — rejected.
        $this->post(route('workflow.ic-batches.store'), [
            'application_ids' => [$application->id],
            'mom_file_path' => 'uploads/mom/1.pdf',
            'amounts' => [$application->id => 9999],
        ])->assertSessionHasErrors('amount');
        $this->assertSame('2500.00', $application->refresh()->amount);

        $this->post(route('workflow.ic-batches.store'), [
            'application_ids' => [$application->id],
            'mom_file_path' => 'uploads/mom/1.pdf',
            'amounts' => [$application->id => 3000],
        ])->assertRedirect();

        $this->assertSame('3000.00', $application->refresh()->amount);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $application->id,
            'action' => 'amount_modified',
        ]);
    }

    public function test_hq_cannot_modify_amount_because_only_ic_role_may_submit_ic_batches(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create(['id' => 1]);
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
            'district_union_id' => 1,
            'amount' => 2500,
        ]);

        $admin = $this->legacyUser(1);
        $this->actingAs($admin);
        $this->post(route('workflow.ic-batches.store'), [
            'application_ids' => [$application->id],
            'mom_file_path' => 'uploads/mom/1.pdf',
            'amounts' => [$application->id => 3000],
        ])->assertForbidden();

        $this->assertSame('2500.00', $application->refresh()->amount);
    }

    public function test_vle_can_delete_own_draft_but_not_an_in_progress_application(): void
    {
        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $vle = $this->legacyUser((int) config('csc.vle_role_id'), ['districtunion' => null, 'samiti' => null, 'circle' => null]);

        $draft = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::Pending->value,
            'applicant_user_id' => $vle->id,
        ]);
        $inProgress = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::RecommendedBySamiti->value,
            'applicant_user_id' => $vle->id,
        ]);

        $this->actingAs($vle);
        $this->delete(route('applications.destroy', $inProgress))->assertForbidden();
        $this->assertDatabaseHas('scholarship_applications', ['id' => $inProgress->id, 'deleted_at' => null]);

        $this->delete(route('applications.destroy', $draft))->assertRedirect(route('applications.index'));
        $this->assertSoftDeleted('scholarship_applications', ['id' => $draft->id]);
        $this->assertDatabaseHas('scholarship_application_audits', [
            'scholarship_application_id' => $draft->id,
            'action' => 'deleted',
        ]);
    }

    public function test_axis_payment_file_is_generated_when_account_submits_a_payment_batch(): void
    {
        $outputPath = storage_path('app/testing-axis-out-'.uniqid());
        Config::set('axis_payment.output_path', $outputPath);

        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $application = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::FinalApplicationForPayment->value,
            'application_number' => 'S-AXIS-TEST-1',
            'amount' => 3000,
            'student_bank_account_number' => '12345678901',
            'student_bank_ifsc' => 'SBIN0001234',
            'student_bank_account_holder_name' => 'Test Student',
        ]);

        $account = $this->legacyUser(6);
        $batch = app(ScholarshipServiceInterface::class)->createPaymentBatch([$application->id], $account, 'Batch for test');

        $this->assertNotNull($batch->axis_file_path);
        $this->assertFileExists($batch->axis_file_path);
        $contents = (string) file_get_contents($batch->axis_file_path);
        $this->assertStringContainsString('S-AXIS-TEST-1', $contents);
        $this->assertStringContainsString('12345678901', $contents);

        File::deleteDirectory($outputPath);
    }

    public function test_reconcile_axis_reverse_feed_command_updates_matched_applications_and_archives_file(): void
    {
        $sourcePath = storage_path('app/testing-reversefeed-src-'.uniqid());
        $archivePath = storage_path('app/testing-reversefeed-archive-'.uniqid());
        mkdir($sourcePath, 0755, true);
        Config::set('axis_payment.reverse_feed_source_path', $sourcePath);
        Config::set('axis_payment.reverse_feed_archive_path', $archivePath);

        $actor = User::factory()->create(['user_type' => 1, 'status' => '1', 'email' => 'axis-actor@example.test']);
        Config::set('axis_payment.reconciliation_actor_email', 'axis-actor@example.test');

        $session = AcademicSession::factory()->create();
        $scheme = Scheme::factory()->create();
        $paid = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::PaymentBatchSubmitted->value,
            'application_number' => 'S-RECON-PAID',
        ]);
        $failed = $this->application($session->id, $scheme->id, [
            'status' => ScholarshipApplicationStatus::PaymentBatchSubmitted->value,
            'application_number' => 'S-RECON-FAILED',
        ]);

        // Real AXIS reverse-feed format: `^`-delimited, no header, no file extension, shared
        // across modules (a Beema reference here simply won't match any application_number and
        // is silently skipped — not an error).
        file_put_contents($sourcePath.'/axis_reversefeed_cgminormki_20260624-164146-464', implode("\n", [
            'S-RECON-PAID^CGMINORSSY^2026-06-24 16:29:27.0^N^AXISCN1385052971^^SUCCESS^Success--UTIBN62026062485393912^38^S-RECON-PAID^2026-06-24 16:41:14.0^CN1385052971^12000^923010071993538^UTIB0000139^D^2002010032090^CGMINORSSY_24062026_1782298764',
            'S-RECON-FAILED^CGMINORSSY^2026-06-24 16:29:27.0^N^^^REJECTED^Beneficiary IFSC code is invalid^38^S-RECON-FAILED^2026-06-24 16:39:01.0^CX0032649836^12000^923010071993538^UTIB0000139^D^6105000100027853^CGMINORSSY_24062026_1782298764',
            'A1236021430^CGMINORSSY^2026-06-24 16:29:27.0^N^AXISCN1385052999^^SUCCESS^Success--UTIBN62026062485399999^38^A1236021430^2026-06-24 16:41:14.0^CN1385052999^12000^923010071993538^UTIB0000139^D^2002010032090^CGMINORSSY_24062026_1782298764',
        ]));

        $this->artisan('scholarship:reconcile-axis-reverse-feed')->assertExitCode(Command::SUCCESS);

        $this->assertSame(ScholarshipApplicationStatus::PaymentCompleted->value, $paid->refresh()->status);
        $this->assertSame('CN1385052971', $paid->payment_reference_id);
        $this->assertSame(ScholarshipApplicationStatus::PaymentFailed->value, $failed->refresh()->status);
        $this->assertSame('Beneficiary IFSC code is invalid', $failed->payment_failure_reason);

        $this->assertFileDoesNotExist($sourcePath.'/axis_reversefeed_cgminormki_20260624-164146-464');
        $this->assertNotEmpty(glob($archivePath.'/*axis_reversefeed_*'));

        File::deleteDirectory($sourcePath);
        File::deleteDirectory($archivePath);
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
            'amount' => 2500,
            'district_union_id' => 1,
            'samiti_id' => 1,
        ], $overrides));
    }
}
