<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Services;

use App\Contracts\Services\AadhaarServiceInterface;
use App\Contracts\Services\WalletServiceInterface;
use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Domains\Scholarship\Enums\ApplicationState;
use App\Domains\Scholarship\Enums\ApprovalState;
use App\Domains\Scholarship\Enums\PaymentAttemptState;
use App\Domains\Scholarship\Enums\PaymentState;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Domains\Scholarship\Enums\SubmissionState;
use App\Domains\Scholarship\Enums\WorkflowStage;
use App\Domains\Scholarship\Enums\WorkflowState;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationAudit;
use App\Models\ScholarshipApplicationDocument;
use App\Models\ScholarshipNotification;
use App\Models\ScholarshipPaymentAttempt;
use App\Models\ScholarshipTendupattaCollection;
use App\Models\ScholarshipWalletTransaction;
use App\Models\ScholarshipWorkflowBatch;
use App\Models\ScholarshipWorkflowTransition;
use App\Models\User;
use App\Services\BaseService;
use App\Services\RoleService;
use App\Services\ScholarshipSessionService;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class ScholarshipService extends BaseService implements ScholarshipServiceInterface
{
    public function __construct(
        private readonly AadhaarServiceInterface $aadhaarService,
        private readonly WalletServiceInterface $walletService,
        private readonly RoleService $roles,
        private readonly ScholarshipSessionService $sessions,
    ) {}

    public function createDraft(array $data, User $user): ScholarshipApplication
    {
        return DB::transaction(function () use ($data, $user): ScholarshipApplication {
            $payload = $this->normalizeApplicationData($data, $user);
            $payload['uuid'] = (string) Str::uuid();
            $payload['applicant_user_id'] = $user->id;
            $payload['created_by'] = $user->id;
            $payload['updated_by'] = $user->id;
            $payload['status'] = ScholarshipApplicationStatus::Pending->value;
            $payload['status_label'] = 'Draft';
            $payload['current_stage'] = 'draft';
            $payload['application_state'] = ApplicationState::Created->value;
            $payload['submission_state'] = SubmissionState::Draft->value;
            $payload['workflow_state'] = WorkflowState::PendingAtVle->value;
            $payload['workflow_stage'] = WorkflowStage::Vle->value;
            $payload['approval_state'] = ApprovalState::Pending->value;
            $payload['payment_state'] = PaymentState::WalletNotStarted->value;
            $payload['is_draft'] = true;

            $application = ScholarshipApplication::query()->create($payload);
            $this->syncChildren($application, $data, false, $user);
            $this->audit($application, 'draft_created', null, 'draft', 'Draft created', $user, $data);

            return $application->refresh();
        });
    }

    public function updateDraft(ScholarshipApplication $application, array $data, User $user): ScholarshipApplication
    {
        if (! $application->is_draft && (int) ($data['scheme_id'] ?? $application->scheme_id) !== (int) $application->scheme_id) {
            throw ValidationException::withMessages([
                'scheme_id' => 'Scholarship Scheme cannot change after final submission.',
            ]);
        }

        return DB::transaction(function () use ($application, $data, $user): ScholarshipApplication {
            $payload = $this->normalizeApplicationData($data, $user, $application);
            $payload['updated_by'] = $user->id;

            $application->fill($payload)->save();
            $this->syncChildren($application, $data, false, $user);
            $this->audit($application, 'draft_updated', (int) $application->status, $application->current_stage, 'Draft updated', $user, $data);

            return $application->refresh();
        });
    }

    public function submit(ScholarshipApplication $application, User $user): ScholarshipApplication
    {
        return DB::transaction(function () use ($application, $user): ScholarshipApplication {
            $this->validateForSubmit($application);

            $status = ScholarshipApplicationStatus::Pending;
            $fromStates = $this->stateSnapshot($application);
            $fromStatus = (int) $application->status;
            $application->fill([
                'application_number' => $application->application_number ?: $this->applicationNumber($application),
                'status' => $status->value,
                'status_label' => $status->label(),
                'current_stage' => $status->stage(),
                ...$this->stateAttributes($status, [
                    'application_state' => ApplicationState::InWorkflow->value,
                    'submission_state' => SubmissionState::Submitted->value,
                    'payment_state' => $application->wallet_paid_at ? PaymentState::WalletSuccess->value : PaymentState::WalletNotRequired->value,
                    'entered_workflow_at' => now(),
                ]),
                'is_draft' => false,
                'submitted_at' => now(),
                'submitted_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            $this->recordWalletEntry($application, $user, 'application_submission', 0, 'SUBMIT-'.$application->id);
            $this->audit($application, 'submitted', $fromStatus, $status->stage(), 'Application finally submitted', $user, $this->withFromStates([], $fromStates));
            $this->notify($application, $user, 'Scholarship application submitted', $status->label());

            return $application->refresh();
        });
    }

    public function prepareWalletSubmission(ScholarshipApplication $application, User $user): ScholarshipApplication
    {
        return DB::transaction(function () use ($application, $user): ScholarshipApplication {
            $this->validateForSubmit($application);
            $fromStates = $this->stateSnapshot($application);
            $transaction = $this->walletService->initiateApplicationFee($application, $user);
            $application->forceFill([
                'application_state' => ApplicationState::Created->value,
                'submission_state' => SubmissionState::WalletPending->value,
                'payment_state' => PaymentState::WalletPending->value,
                'updated_by' => $user->id,
            ])->save();
            $this->recordPaymentAttempt($application->refresh(), $transaction, PaymentAttemptState::Pending, $user);

            $this->audit($application, 'wallet_payment_initiated', (int) $application->status, 'wallet', 'CSC wallet payment initiated', $user, [
                ...$this->withFromStates([], $fromStates),
                'wallet_transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
            ]);

            return $application->refresh();
        });
    }

    public function completeWalletSubmission(ScholarshipApplication $application, array $walletResponse, User $user): ScholarshipApplication
    {
        return DB::transaction(function () use ($application, $walletResponse, $user): ScholarshipApplication {
            $success = ($walletResponse['txn_status_message'] ?? null) === 'Success' || ($walletResponse['txn_status'] ?? null) === 'Success';

            if (! $success) {
                $transaction = $this->walletService->failApplicationFee($application, $walletResponse, $user);
                $fromStates = $this->stateSnapshot($application);
                $application->forceFill([
                    'application_state' => ApplicationState::Created->value,
                    'submission_state' => SubmissionState::WalletPending->value,
                    'payment_state' => PaymentState::WalletFailed->value,
                    'updated_by' => $user->id,
                ])->save();
                $this->recordPaymentAttempt($application->refresh(), $transaction, PaymentAttemptState::Failed, $user, $walletResponse);
                $this->audit($application, 'wallet_payment_failed', (int) $application->status, 'wallet', 'CSC wallet payment failed or cancelled', $user, [
                    ...$this->withFromStates([], $fromStates),
                    'wallet_transaction_id' => $transaction->id,
                    'response' => $walletResponse,
                ]);

                throw ValidationException::withMessages(['wallet' => 'CSC wallet payment was not completed.']);
            }

            $transaction = $this->walletService->completeApplicationFee($application, $walletResponse, $user);
            $fromStates = $this->stateSnapshot($application);
            $application->forceFill([
                'wallet_paid_at' => now(),
                'application_state' => ApplicationState::Submitted->value,
                'submission_state' => SubmissionState::Submitted->value,
                'payment_state' => PaymentState::WalletSuccess->value,
                'updated_by' => $user->id,
            ])->save();
            $this->recordPaymentAttempt($application->refresh(), $transaction, PaymentAttemptState::Completed, $user, $walletResponse);
            $submitted = $this->submit($application->refresh(), $user);
            $this->audit($submitted, 'wallet_payment_completed', (int) $application->status, 'wallet', 'CSC wallet payment completed', $user, [
                ...$this->withFromStates([], $fromStates),
                'wallet_transaction_id' => $transaction->id,
                'response' => $walletResponse,
            ]);

            return $submitted->refresh();
        });
    }

    public function resubmit(ScholarshipApplication $application, array $data, User $user): ScholarshipApplication
    {
        if (! in_array((int) $application->status, [
            ScholarshipApplicationStatus::RejectedBySamiti->value,
            ScholarshipApplicationStatus::RejectedByIC->value,
            ScholarshipApplicationStatus::RejectedByDistrictUnion->value,
            ScholarshipApplicationStatus::RejectedByHQ->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByAccounts->value,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Only returned applications can be resubmitted.']);
        }

        return DB::transaction(function () use ($application, $data, $user): ScholarshipApplication {
            $payload = $this->normalizeApplicationData($data, $user, $application);
            $fromStates = $this->stateSnapshot($application);
            $fromStatus = (int) $application->status;
            $status = $fromStatus === ScholarshipApplicationStatus::PermanentlyRejectedByAccounts->value
                ? ScholarshipApplicationStatus::AccountDetailsUpdatedByHQ
                : ScholarshipApplicationStatus::Resubmitted;
            $this->enforceEditableDocumentsForReturn($application, $data);

            $application->fill($payload + [
                'status' => $status->value,
                'status_label' => $status->label(),
                'current_stage' => $status->stage(),
                ...$this->stateAttributes($status, [
                    'application_state' => ApplicationState::InWorkflow->value,
                    'submission_state' => SubmissionState::Resubmitted->value,
                    'returned_at' => null,
                    'rejected_at' => null,
                ]),
                'is_draft' => false,
                'metadata' => array_diff_key($this->applicationMetadata($application), array_flip(['correction_sections', 'editable_documents', 'returned_at', 'returned_by'])),
                'updated_by' => $user->id,
            ])->save();

            $this->syncChildren($application, $data, false, $user);
            ScholarshipApplicationDocument::query()
                ->where('scholarship_application_id', $application->id)
                ->where('is_current', true)
                ->update(['editable_after_return' => false]);
            $this->validateForSubmit($application->refresh());
            $this->audit($application, 'resubmitted', $fromStatus, $status->stage(), 'Application resubmitted after return', $user, $this->withFromStates($data, $fromStates));
            $this->notify($application, $user, 'Scholarship application resubmitted', $status->label());

            return $application->refresh();
        });
    }

    public function deleteDraft(ScholarshipApplication $application, User $user, ?string $remarks = null): void
    {
        if (! in_array((int) $application->status, [
            ScholarshipApplicationStatus::Pending->value,
            ScholarshipApplicationStatus::Resubmitted->value,
            ScholarshipApplicationStatus::PermanentlyRejectedBySamiti->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByIC->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByCCF->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByDistrictUnion->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByHQ->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByAccounts->value,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft or permanently rejected applications can be deleted.']);
        }

        DB::transaction(function () use ($application, $user, $remarks): void {
            $fromStatus = (int) $application->status;
            $this->audit($application, 'deleted', $fromStatus, $application->current_stage, $remarks ?: 'Application deleted by VLE', $user);
            $application->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function enforceEditableDocumentsForReturn(ScholarshipApplication $application, array $data): void
    {
        $allowed = $this->applicationMetadata($application)['editable_documents'] ?? [];
        if ($allowed === []) {
            return;
        }

        $attempted = array_keys($data['documents'] ?? []);
        $blocked = array_values(array_diff(array_map('strval', $attempted), array_map('strval', $allowed)));

        if ($blocked !== []) {
            throw ValidationException::withMessages([
                'documents' => 'Only documents selected during return for correction can be replaced: '.implode(', ', $allowed).'.',
            ]);
        }
    }

    public function transition(ScholarshipApplication $application, string $action, ?string $remarks, User $user, array $correctionSections = [], array $editableDocuments = []): ScholarshipApplication
    {
        return DB::transaction(function () use ($application, $action, $remarks, $user, $correctionSections, $editableDocuments): ScholarshipApplication {
            $status = $this->nextStatus($application, $action);
            $fromStates = $this->stateSnapshot($application);
            $fromStatus = (int) $application->status;
            $selectedSections = array_values(array_unique(array_filter(array_map('strval', $correctionSections))));
            $selectedDocuments = array_values(array_unique(array_filter(array_map('strval', $editableDocuments))));

            $updates = [
                'status' => $status->value,
                'status_label' => $status->label(),
                'current_stage' => $status->stage(),
                ...$this->stateAttributes($status),
                'updated_by' => $user->id,
            ];

            if ($status === ScholarshipApplicationStatus::RecommendedBySamiti) {
                $updates['tendupatta_verified_at'] = now();
                $updates['tendupatta_verified_by'] = $user->id;
                ScholarshipTendupattaCollection::query()
                    ->where('scholarship_application_id', $application->id)
                    ->update(['is_verified' => true, 'verified_by' => $user->id, 'verified_at' => now()]);
            }

            if ($status === ScholarshipApplicationStatus::PaymentBatchSubmitted) {
                $updates['payment_status'] = 'submitted';
            }

            if ($action === 'return') {
                $metadata = $this->applicationMetadata($application);
                $metadata['correction_sections'] = $selectedSections;
                $metadata['editable_documents'] = $selectedDocuments;
                $metadata['returned_at'] = now()->toDateTimeString();
                $metadata['returned_by'] = $user->id;
                $updates['metadata'] = $metadata;

                ScholarshipApplicationDocument::query()
                    ->where('scholarship_application_id', $application->id)
                    ->where('is_current', true)
                    ->update(['editable_after_return' => false]);

                if ($selectedDocuments !== []) {
                    ScholarshipApplicationDocument::query()
                        ->where('scholarship_application_id', $application->id)
                        ->where('is_current', true)
                        ->whereIn('document_type', $selectedDocuments)
                        ->update(['editable_after_return' => true]);
                }
            }

            $application->fill($updates)->save();
            $this->audit($application, $action, $fromStatus, $status->stage(), $remarks ?: $status->label(), $user, [
                ...$this->withFromStates([], $fromStates),
                'correction_sections' => $selectedSections,
                'editable_documents' => $selectedDocuments,
            ]);
            $this->notify($application, $user, 'Scholarship workflow updated', $status->label());

            return $application->refresh();
        });
    }

    /**
     * @param  list<int>  $applicationIds
     * @param  array<int, int>  $amountOverrides  application_id => IC-selected award amount
     */
    public function createIcBatch(array $applicationIds, User $user, ?string $momFilePath = null, ?string $remarks = null, array $amountOverrides = []): ScholarshipWorkflowBatch
    {
        if ($momFilePath === null || trim($momFilePath) === '') {
            throw ValidationException::withMessages(['mom_file_path' => 'IC batch MoM document is mandatory.']);
        }

        return $this->createBatch('IC', $applicationIds, ScholarshipApplicationStatus::RecommendedBySamiti, $user, $momFilePath, $remarks, $amountOverrides);
    }

    public function createPaymentBatch(array $applicationIds, User $user, ?string $remarks = null): ScholarshipWorkflowBatch
    {
        return $this->createBatch('PAYMENT', $applicationIds, ScholarshipApplicationStatus::FinalApplicationForPayment, $user, null, $remarks);
    }

    /**
     * Scheme-fixed award amounts an IC user may pick from when modifying an application's
     * amount during batch verification — mirrors legacy's `scheme_helper.php::getAmount()`
     * allow-list exactly. Never free text: the caller must select one of these values.
     *
     * @return list<int>
     */
    public function amountOptionsForScheme(int $schemeId): array
    {
        return match ($schemeId) {
            1 => [2500, 3000],
            2 => [15000, 25000],
            3 => [5000, 10000],
            default => [3000, 4000, 5000],
        };
    }

    public function recordPaymentResult(ScholarshipApplication $application, bool $success, ?string $reference, ?string $failureReason, User $user, array $bankResponse = []): ScholarshipApplication
    {
        return DB::transaction(function () use ($application, $success, $reference, $failureReason, $user, $bankResponse): ScholarshipApplication {
            $status = $success ? ScholarshipApplicationStatus::PaymentCompleted : ScholarshipApplicationStatus::PaymentFailed;
            $fromStates = $this->stateSnapshot($application);
            $fromStatus = (int) $application->status;

            $application->fill([
                'status' => $status->value,
                'status_label' => $status->label(),
                'current_stage' => $status->stage(),
                ...$this->stateAttributes($status, [
                    'application_state' => $success ? ApplicationState::Completed->value : ApplicationState::InWorkflow->value,
                    'payment_state' => $success ? PaymentState::BeneficiaryPaymentSuccess->value : PaymentState::BeneficiaryPaymentFailed->value,
                    'completed_at' => $success ? now() : null,
                ]),
                'payment_status' => $success ? 'success' : 'failed',
                'payment_reference_id' => $reference,
                'payment_failure_reason' => $success ? null : $failureReason,
                'paid_at' => $success ? now() : null,
                'updated_by' => $user->id,
            ])->save();

            $this->recordBeneficiaryPaymentAttempt(
                $application->refresh(),
                $success ? PaymentAttemptState::Completed : PaymentAttemptState::Failed,
                $user,
                $reference,
                $failureReason,
                [
                    ...$bankResponse,
                    'payment_txn_reference' => $reference,
                    'payment_date' => $success ? now()->toDateTimeString() : null,
                    'failure_reason' => $failureReason,
                    'bank_status' => $success ? 'success' : 'failed',
                ],
            );
            $this->updateLatestPaymentBatchRow($application, $success, $failureReason);
            $this->audit($application, $success ? 'payment_success' : 'payment_failed', $fromStatus, $status->stage(), $failureReason ?: $status->label(), $user, $this->withFromStates([], $fromStates));

            return $application->refresh();
        });
    }

    private function normalizeApplicationData(array $data, User $user, ?ScholarshipApplication $application = null): array
    {
        $studentAadhaar = preg_replace('/\D/', '', (string) $this->inputValue($data, 'student_aadhaar', $application, ''));
        $studentName = trim((string) $this->inputValue($data, 'student_name', $application, ''));
        $aadhaar = $this->aadhaarService->verifyStudent($studentAadhaar, $studentName);

        if (! $aadhaar['verified']) {
            throw ValidationException::withMessages(['student_aadhaar' => 'Student Aadhaar must be a valid 12 digit number and must verify against the student name.']);
        }

        $marksObtained = $this->nullableFloat($this->inputValue($data, 'marks_obtained', $application));
        $maximumMarks = $this->nullableFloat($this->inputValue($data, 'maximum_marks', $application));
        $percentage = $maximumMarks !== null && $maximumMarks > 0 && $marksObtained !== null
            ? round(($marksObtained / $maximumMarks) * 100, 2)
            : null;

        $schemeId = (int) $this->inputValue($data, 'scheme_id', $application, 0);
        $class = (string) $this->inputValue($data, 'class', $application, '');
        $yearOfStudy = (int) $this->inputValue($data, 'current_year_of_study', $application, 0);
        $academicSession = $this->sessions->deriveForDate($application?->created_at ?? now());
        $scholarshipSession = $academicSession;

        if ($academicSession === null || $scholarshipSession === null) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic Session master is not configured for the application date.',
            ]);
        }

        $duplicateSession = ScholarshipApplication::query()
            ->where('student_aadhaar', $studentAadhaar)
            ->where('scholarship_session_id', $scholarshipSession->id)
            ->when($application, fn ($query) => $query->whereKeyNot($application->id))
            ->exists();

        if ($duplicateSession) {
            throw ValidationException::withMessages([
                'student_aadhaar' => 'One Student Aadhaar can have only one scholarship application in one Scholarship Session.',
            ]);
        }

        return [
            'academic_session_id' => $academicSession->id,
            'scholarship_session_id' => $scholarshipSession->id,
            'scheme_id' => $schemeId,
            'district_id' => $this->nullableInt($this->inputValue($data, 'district_id', $application)),
            'district_union_id' => $this->nullableInt($this->inputValue($data, 'district_union_id', $application, $user->districtunion)),
            'samiti_id' => $this->nullableInt($this->inputValue($data, 'samiti_id', $application, $user->samiti)),
            'phad_id' => $this->nullableInt($this->inputValue($data, 'phad_id', $application)),
            'tendupatta_data_source' => strtoupper((string) $this->inputValue($data, 'tendupatta_data_source', $application, 'MANUAL')),
            'student_aadhaar' => $studentAadhaar,
            'aadhaar_verified_student_name' => $aadhaar['name'],
            'student_name' => $studentName,
            'gender' => $this->inputValue($data, 'gender', $application),
            'date_of_birth' => $this->inputValue($data, 'date_of_birth', $application),
            'mobile' => $this->inputValue($data, 'mobile', $application),
            'address' => $this->inputValue($data, 'address', $application),
            'pincode' => $this->digitsOrNull($this->inputValue($data, 'pincode', $application)),
            'block_code' => $this->inputValue($data, 'block_code', $application),
            'area' => $this->inputValue($data, 'area', $application),
            'gram_panchayat_code' => $this->inputValue($data, 'gram_panchayat_code', $application),
            'village_code' => $this->inputValue($data, 'village_code', $application),
            'city_code' => $this->inputValue($data, 'city_code', $application),
            'ward_code' => $this->inputValue($data, 'ward_code', $application),
            'ward_number' => $this->inputValue($data, 'ward_number', $application),
            'class' => $class,
            'school_college_name' => $this->inputValue($data, 'school_college_name', $application),
            'board_university' => $this->inputValue($data, 'board_university', $application),
            'roll_number' => $this->inputValue($data, 'roll_number', $application),
            'marks_obtained' => $marksObtained,
            'maximum_marks' => $maximumMarks,
            'percentage' => $percentage,
            'course_name' => $this->inputValue($data, 'course_name', $application),
            'course_duration' => $this->nullableInt($this->inputValue($data, 'course_duration', $application)),
            'institution_name' => $this->inputValue($data, 'institution_name', $application),
            'admission_year' => $this->nullableInt($this->inputValue($data, 'admission_year', $application)),
            'first_year_session' => $this->inputValue($data, 'first_year_session', $application),
            'scholarship_session' => $scholarshipSession->name,
            'current_year_of_study' => $yearOfStudy ?: null,
            'sangrahak_card_number' => $this->inputValue($data, 'sangrahak_card_number', $application),
            'head_of_family_aadhaar' => array_key_exists('head_of_family_aadhaar', $data) ? preg_replace('/\D/', '', (string) $data['head_of_family_aadhaar']) : $this->inputValue($data, 'head_of_family_aadhaar', $application),
            'head_of_family_name' => $this->inputValue($data, 'head_of_family_name', $application),
            'head_of_family_father_or_husband_name' => $this->inputValue($data, 'head_of_family_father_or_husband_name', $application),
            'head_of_family_gender' => $this->inputValue($data, 'head_of_family_gender', $application),
            'head_of_family_date_of_birth' => $this->inputValue($data, 'head_of_family_date_of_birth', $application),
            'student_bank_account_number' => preg_replace('/\D/', '', (string) $this->inputValue($data, 'student_bank_account_number', $application, '')) ?: null,
            'student_bank_ifsc' => strtoupper((string) $this->inputValue($data, 'student_bank_ifsc', $application, '')) ?: null,
            'student_bank_name' => $this->inputValue($data, 'student_bank_name', $application),
            'student_bank_branch' => $this->inputValue($data, 'student_bank_branch', $application),
            'student_bank_account_holder_name' => $this->inputValue($data, 'student_bank_account_holder_name', $application),
            'amount' => $this->calculateAmount($schemeId, $class, $yearOfStudy),
        ];
    }

    private function validateForSubmit(ScholarshipApplication $application): void
    {
        $errors = [];

        if ($application->student_bank_account_number === null || $application->student_bank_ifsc === null) {
            $errors['student_bank_account_number'] = 'Student own bank account details are mandatory.';
        }

        if ($this->normalizeName((string) $application->student_bank_account_holder_name) !== $this->normalizeName($application->aadhaar_verified_student_name)) {
            $errors['student_bank_account_holder_name'] = 'Account holder name must match Aadhaar-verified student name.';
        }

        if (in_array((int) $application->scheme_id, [1, 2], true) && ! in_array((string) $application->class, ['10', '12'], true)) {
            $errors['class'] = 'Scheme 1 and Scheme 2 are allowed only for Class 10 or Class 12.';
        }

        foreach ([
            'district_union_id' => 'District Union is mandatory.',
            'samiti_id' => 'Samiti is mandatory.',
            'phad_id' => 'Phad is mandatory.',
            'district_id' => 'District is mandatory.',
            'block_code' => 'Block is mandatory.',
            'area' => 'Area is mandatory.',
            'student_name' => 'Student name is mandatory.',
            'gender' => 'Gender is mandatory.',
            'date_of_birth' => 'Student date of birth is mandatory.',
            'mobile' => 'Contact number is mandatory.',
            'address' => 'Address is mandatory.',
            'pincode' => 'Pincode is mandatory.',
            'sangrahak_card_number' => 'Sangrahak card number is mandatory.',
            'head_of_family_aadhaar' => 'Head of Family Aadhaar is mandatory.',
            'head_of_family_name' => 'Head of Family name is mandatory.',
            'school_college_name' => 'School or college name is mandatory.',
            'marks_obtained' => 'Marks obtained is mandatory.',
            'maximum_marks' => 'Maximum marks is mandatory.',
        ] as $field => $message) {
            if ($application->{$field} === null || $application->{$field} === '') {
                $errors[$field] = $message;
            }
        }

        if ($application->area === 'Rural' && ($application->gram_panchayat_code === null || $application->village_code === null)) {
            $errors['gram_panchayat_code'] = 'Gram Panchayat and Village are mandatory for Rural applications.';
        }

        if ($application->area === 'Urban' && ($application->city_code === null || $application->ward_code === null)) {
            $errors['city_code'] = 'City and Ward are mandatory for Urban applications.';
        }

        if (strlen((string) $application->pincode) !== 6) {
            $errors['pincode'] = 'Pincode must be 6 digits.';
        }

        if (strlen((string) $application->mobile) !== 10) {
            $errors['mobile'] = 'Contact number must be 10 digits.';
        }

        if ($application->student_aadhaar === $application->head_of_family_aadhaar) {
            $errors['head_of_family_aadhaar'] = 'Student and Head of Family Aadhaar cannot be same.';
        }

        if (in_array((int) $application->scheme_id, [3, 4], true)) {
            foreach ([
                'course_name' => 'Course name is mandatory.',
                'course_duration' => 'Course duration is mandatory.',
                'institution_name' => 'Institute name is mandatory.',
                'board_university' => 'University name is mandatory.',
                'current_year_of_study' => 'Education year is mandatory.',
            ] as $field => $message) {
                if ($application->{$field} === null || $application->{$field} === '') {
                    $errors[$field] = $message;
                }
            }
        }

        foreach ($this->requiredDocumentTypes((int) $application->scheme_id) as $documentType) {
            $hasDocument = ScholarshipApplicationDocument::query()
                ->where('scholarship_application_id', $application->id)
                ->where('document_type', $documentType)
                ->where('is_current', true)
                ->whereNotNull('file_path')
                ->where('file_path', '!=', '')
                ->exists();

            if (! $hasDocument) {
                $errors['documents.'.$documentType] = $this->documentLabel($documentType).' is mandatory.';
            }
        }

        $duplicateSession = ScholarshipApplication::query()
            ->where('student_aadhaar', $application->student_aadhaar)
            ->where('scholarship_session_id', $application->scholarship_session_id)
            ->whereKeyNot($application->id)
            ->exists();

        if ($duplicateSession) {
            $errors['student_aadhaar'] = 'One Student Aadhaar can have only one scholarship application in one Scholarship Session.';
        }

        $sameAadhaarDifferentBank = ScholarshipApplication::query()
            ->where('student_aadhaar', $application->student_aadhaar)
            ->whereNotNull('student_bank_account_number')
            ->where('student_bank_account_number', '!=', $application->student_bank_account_number)
            ->whereKeyNot($application->id)
            ->exists();

        if ($sameAadhaarDifferentBank) {
            $errors['student_bank_account_number'] = 'One Student Aadhaar maps to one bank account.';
        }

        $sameBankDifferentAadhaar = ScholarshipApplication::query()
            ->where('student_bank_account_number', $application->student_bank_account_number)
            ->where('student_aadhaar', '!=', $application->student_aadhaar)
            ->whereKeyNot($application->id)
            ->exists();

        if ($sameBankDifferentAadhaar) {
            $errors['student_bank_account_number'] = 'One bank account cannot link to multiple Student Aadhaar numbers.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function nextStatus(ScholarshipApplication $application, string $action): ScholarshipApplicationStatus
    {
        $current = ScholarshipApplicationStatus::tryFrom((int) $application->status);
        $key = ($current instanceof ScholarshipApplicationStatus ? $current->name : 'Unknown').':'.$action;

        $status = match ($key) {
            'Pending:recommend', 'Resubmitted:recommend' => ScholarshipApplicationStatus::RecommendedBySamiti,
            'Pending:return', 'Resubmitted:return' => ScholarshipApplicationStatus::RejectedBySamiti,
            'Pending:reject', 'Resubmitted:reject' => ScholarshipApplicationStatus::PermanentlyRejectedBySamiti,
            'RecommendedBySamiti:recommend' => ScholarshipApplicationStatus::RecommendedByIC,
            'RecommendedBySamiti:return' => ScholarshipApplicationStatus::RejectedByIC,
            'RecommendedBySamiti:reject' => ScholarshipApplicationStatus::PermanentlyRejectedByIC,
            'RecommendedByIC:recommend' => ScholarshipApplicationStatus::RecommendedByDistrictUnion,
            'RecommendedByIC:return' => ScholarshipApplicationStatus::RejectedByDistrictUnion,
            'RecommendedByIC:reject' => ScholarshipApplicationStatus::PermanentlyRejectedByDistrictUnion,
            'RecommendedByDistrictUnion:recommend' => ScholarshipApplicationStatus::RecommendedForPayment,
            'RecommendedByDistrictUnion:return' => ScholarshipApplicationStatus::RejectedByHQ,
            'RecommendedByDistrictUnion:reject' => ScholarshipApplicationStatus::PermanentlyRejectedByHQ,
            'RecommendedForPayment:forward' => ScholarshipApplicationStatus::FinalApplicationForPayment,
            'FinalApplicationForPayment:remove' => ScholarshipApplicationStatus::RecommendedForPayment,
            'FinalApplicationForPayment:submit_payment_batch' => ScholarshipApplicationStatus::PaymentBatchSubmitted,
            'PaymentFailed:retry' => ScholarshipApplicationStatus::RecommendedForPayment,
            'AccountDetailsUpdatedByHQ:recommend' => ScholarshipApplicationStatus::RecommendedForPayment,
            default => null,
        };

        if (! $status instanceof ScholarshipApplicationStatus) {
            throw ValidationException::withMessages(['action' => 'This workflow action is not valid for the current application status.']);
        }

        return $status;
    }

    /**
     * @param  list<int>  $applicationIds
     * @param  array<int, int>  $amountOverrides  application_id => IC-selected award amount (IC batches only)
     */
    private function createBatch(string $type, array $applicationIds, ScholarshipApplicationStatus $requiredStatus, User $user, ?string $momFilePath, ?string $remarks, array $amountOverrides = []): ScholarshipWorkflowBatch
    {
        return DB::transaction(function () use ($type, $applicationIds, $requiredStatus, $user, $momFilePath, $remarks, $amountOverrides): ScholarshipWorkflowBatch {
            $applications = ScholarshipApplication::query()
                ->whereIn('id', array_unique(array_map('intval', $applicationIds)))
                ->where('status', $requiredStatus->value)
                ->get();

            if ($applications->count() !== count(array_unique($applicationIds))) {
                throw ValidationException::withMessages(['application_ids' => 'All applications must be in the required workflow status for this batch.']);
            }

            if ($type === 'IC') {
                foreach ($applications as $application) {
                    $override = $amountOverrides[$application->id] ?? null;
                    if ($override !== null && (int) $override !== (int) $application->amount) {
                        $this->modifyAmount($application, (int) $override, $user);
                    }
                }
            }

            $batch = ScholarshipWorkflowBatch::query()->create([
                'uuid' => (string) Str::uuid(),
                'batch_number' => $type.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'type' => $type,
                'status' => 'SUBMITTED',
                'mom_file_path' => $momFilePath,
                'remarks' => $remarks,
                'total_applications' => $applications->count(),
                'total_amount' => $applications->sum('amount'),
                'created_by' => $user->id,
                'submitted_at' => now(),
            ]);

            foreach ($applications as $application) {
                $batch->applications()->create([
                    'scholarship_application_id' => $application->id,
                    'amount' => $application->amount,
                    'payment_status' => $type === 'PAYMENT' ? 'submitted' : null,
                ]);

                if ($type === 'PAYMENT') {
                    $application = $this->transition($application, 'submit_payment_batch', $remarks, $user);
                    $this->recordBeneficiaryPaymentAttempt(
                        $application,
                        PaymentAttemptState::Submitted,
                        $user,
                        $batch->batch_number,
                        null,
                        ['batch_id' => $batch->id, 'batch_number' => $batch->batch_number],
                    );
                } else {
                    $this->audit($application, 'ic_batch_submitted', (int) $application->status, $application->current_stage, $remarks ?: 'IC batch submitted with MoM', $user, ['batch_id' => $batch->id]);
                }
            }

            if ($type === 'PAYMENT') {
                $this->generateAxisPaymentFile($batch, $applications, $user);
            }

            return $batch->refresh();
        });
    }

    /**
     * Writes the AXIS bank payment-instruction file for a payment batch — a pipe-delimited
     * fixed-format .txt file, one line per application, matching legacy `Payment::finishpayment()`
     * exactly (field order/positions matter: an external scheduler process consumes this file).
     * Output directory is configurable (`axis_payment.output_path`) since legacy hardcoded a
     * server-local path outside the web root.
     *
     * @param  EloquentCollection<int, ScholarshipApplication>  $applications
     */
    private function generateAxisPaymentFile(ScholarshipWorkflowBatch $batch, EloquentCollection $applications, User $user): void
    {
        $directory = (string) config('axis_payment.output_path');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = $batch->batch_number.'.txt';
        $lines = $applications->map(fn (ScholarshipApplication $application): string => $this->axisPaymentLine($application, $user))->implode(PHP_EOL);

        file_put_contents($directory.DIRECTORY_SEPARATOR.$fileName, $lines.PHP_EOL);

        $batch->forceFill([
            'axis_file_path' => $directory.DIRECTORY_SEPARATOR.$fileName,
            'axis_file_generated_at' => now(),
        ])->save();
    }

    private function axisPaymentLine(ScholarshipApplication $application, User $user): string
    {
        $address = trim((string) preg_replace('/\s+/', ' ', (string) $application->address));
        $fields = [
            'P', 'NE', (string) config('axis_payment.vendor_code'),
            (string) $application->application_number,
            (string) config('axis_payment.debit_account_number'),
            now()->format('Y-m-d'),
            'INR',
            (string) $application->amount,
            (string) $application->student_bank_account_holder_name,
            (string) $application->application_number,
            (string) $application->student_bank_account_number,
            '10',
            $address, $address, $address,
            (string) $application->district?->name,
            (string) config('axis_payment.state_name'),
            (string) $application->pincode,
            (string) $application->student_bank_ifsc,
            (string) $application->student_bank_name,
            '', '', '', '', '',
            (string) config('axis_payment.remitter_email'),
            '', '', '', '', '', '', '', '', '',
            'VEND', '',
            now()->format('Y-m-d H-i-s'),
            (string) $user->id,
            '', '', '', '', '',
        ];

        return implode('^', $fields);
    }

    /**
     * IC-only award amount override during IC batch (MoM) verification. The amount must be one
     * of the scheme's fixed values (`amountOptionsForScheme()`) — never free text — and the audit
     * trail records both the standard, automatically-calculated amount and the IC-modified amount.
     */
    private function modifyAmount(ScholarshipApplication $application, int $amount, User $user): void
    {
        $standardAmount = (int) $application->amount;
        $options = $this->amountOptionsForScheme((int) $application->scheme_id);

        if (! in_array($amount, $options, true)) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be one of the scheme-defined values: '.implode(', ', $options).'.',
            ]);
        }

        $application->fill(['amount' => $amount, 'updated_by' => $user->id])->save();

        $this->audit($application, 'amount_modified', (int) $application->status, $application->current_stage, 'Award amount modified by IC', $user, [
            'standard_amount' => $standardAmount,
            'previous_amount' => $standardAmount,
            'modified_amount' => $amount,
        ]);
    }

    /**
     * @return list<string>
     */
    private function requiredDocumentTypes(int $schemeId): array
    {
        $documents = ['tpcard', 'haadharcard', 'aadharcard', 'admission_copy', 'passbook'];

        if (in_array($schemeId, [3, 4], true)) {
            $documents[] = 'admission_receipt';
        }

        return $documents;
    }

    private function documentLabel(string $documentType): string
    {
        return match ($documentType) {
            'tpcard' => 'Sangrahak Card',
            'haadharcard' => 'Head of Family Aadhaar Card',
            'aadharcard' => 'Student Aadhaar Card',
            'admission_copy' => 'Marksheet Copy',
            'passbook' => 'Student Bank Passbook',
            'admission_receipt' => 'Admission Receipt',
            default => str_replace('_', ' ', $documentType),
        };
    }

    private function syncChildren(ScholarshipApplication $application, array $data, bool $verified, User $user): void
    {
        foreach ($data['documents'] ?? [] as $documentType => $document) {
            if (! is_array($document)) {
                $document = ['file_path' => $document];
            }

            $filePath = trim((string) ($document['file_path'] ?? ''));
            if ($filePath === '') {
                continue;
            }

            $this->storeDocumentVersion($application, (string) $documentType, $document, $verified, $user);
        }

        foreach ($data['tendupatta_collections'] ?? [] as $collection) {
            if (! is_array($collection) || empty($collection['collection_year'])) {
                continue;
            }

            ScholarshipTendupattaCollection::query()->updateOrCreate(
                ['scholarship_application_id' => $application->id, 'collection_year' => (string) $collection['collection_year']],
                [
                    'quantity_gaddi' => (float) ($collection['quantity_gaddi'] ?? 0),
                    'data_source' => strtoupper((string) ($collection['data_source'] ?? 'MANUAL')),
                    'is_verified' => $verified,
                    'verified_by' => $verified ? $user->id : null,
                    'verified_at' => $verified ? now() : null,
                    'remarks' => $collection['remarks'] ?? null,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function storeDocumentVersion(ScholarshipApplication $application, string $documentType, array $document, bool $verified, User $user): void
    {
        $current = ScholarshipApplicationDocument::query()
            ->where('scholarship_application_id', $application->id)
            ->where('document_type', $documentType)
            ->where('is_current', true)
            ->latest('version')
            ->first();

        if ($current instanceof ScholarshipApplicationDocument) {
            $current->forceFill([
                'is_current' => false,
                'replaced_by' => $user->id,
                'replaced_at' => now(),
                'editable_after_return' => false,
            ])->save();
        }

        $filePath = (string) $document['file_path'];
        $storedFileName = (string) ($document['stored_file_name'] ?? basename($filePath));
        $extension = (string) ($document['file_extension'] ?? pathinfo($filePath, PATHINFO_EXTENSION));

        $version = $current instanceof ScholarshipApplicationDocument ? $current->version + 1 : 1;

        $created = ScholarshipApplicationDocument::query()->create([
            'scholarship_application_id' => $application->id,
            'student_identifier' => $application->student_aadhaar,
            'scheme_id' => $application->scheme_id,
            'document_type' => $documentType,
            'file_path' => $filePath,
            'storage_disk' => (string) ($document['storage_disk'] ?? 'public'),
            'original_file_name' => $document['original_file_name'] ?? $storedFileName,
            'stored_file_name' => $storedFileName,
            'file_extension' => $extension !== '' ? strtolower($extension) : null,
            'mime_type' => $document['mime_type'] ?? $this->mimeTypeFromExtension($extension),
            'file_size' => isset($document['file_size']) ? (int) $document['file_size'] : null,
            'source' => strtoupper((string) ($document['source'] ?? 'MANUAL')),
            'uploaded_by' => $document['uploaded_by'] ?? $user->id,
            'uploaded_at' => $document['uploaded_at'] ?? now(),
            'is_verified' => $verified,
            'verified_by' => $verified ? $user->id : null,
            'verified_at' => $verified ? now() : null,
            'remarks' => $document['remarks'] ?? null,
            'version' => $version,
            'is_current' => true,
            'previous_document_id' => $current?->id,
            'editable_after_return' => false,
        ]);

        $this->audit($application, $current instanceof ScholarshipApplicationDocument ? 'document_replaced' : 'document_uploaded', (int) $application->status, $application->current_stage, $this->documentLabel($documentType), $user, [
            'document_id' => $created->id,
            'document_type' => $documentType,
            'version' => $created->version,
            'previous_document_id' => $current?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationMetadata(ScholarshipApplication $application): array
    {
        $rawMetadata = $application->getRawOriginal('metadata');
        $decoded = is_string($rawMetadata) ? json_decode($rawMetadata, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function mimeTypeFromExtension(?string $extension): string
    {
        return match (strtolower((string) $extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function audit(ScholarshipApplication $application, string $action, ?int $fromStatus, ?string $stage, ?string $remarks, User $user, array $payload = []): void
    {
        $fromStates = $payload['_from_states'] ?? [];
        unset($payload['_from_states']);

        ScholarshipApplicationAudit::query()->create([
            'scholarship_application_id' => $application->id,
            'from_status' => $fromStatus,
            'to_status' => (int) $application->status,
            'action' => $action,
            'stage' => $stage,
            'remarks' => $remarks,
            'acted_by' => $user->id,
            'acted_at' => now(),
            'payload' => $payload ?: null,
        ]);

        ScholarshipWorkflowTransition::query()->create([
            'scholarship_application_id' => $application->id,
            'from_application_state' => $fromStates['application_state'] ?? null,
            'to_application_state' => $this->stateValue($application->application_state),
            'from_workflow_state' => $fromStates['workflow_state'] ?? null,
            'to_workflow_state' => $this->stateValue($application->workflow_state),
            'from_workflow_stage' => $fromStates['workflow_stage'] ?? null,
            'to_workflow_stage' => $this->stateValue($application->workflow_stage),
            'from_payment_state' => $fromStates['payment_state'] ?? null,
            'to_payment_state' => $this->stateValue($application->payment_state),
            'from_approval_state' => $fromStates['approval_state'] ?? null,
            'to_approval_state' => $this->stateValue($application->approval_state),
            'action' => $action,
            'remarks' => $remarks,
            'acted_by' => $user->id,
            'acted_by_role' => $this->roles->name($user),
            'acted_at' => now(),
            'payload' => $payload ?: null,
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function stateSnapshot(ScholarshipApplication $application): array
    {
        return [
            'application_state' => $this->stateValue($application->application_state),
            'submission_state' => $this->stateValue($application->submission_state),
            'workflow_state' => $this->stateValue($application->workflow_state),
            'workflow_stage' => $this->stateValue($application->workflow_stage),
            'approval_state' => $this->stateValue($application->approval_state),
            'payment_state' => $this->stateValue($application->payment_state),
        ];
    }

    private function stateValue(mixed $state): ?string
    {
        if ($state instanceof BackedEnum) {
            return (string) $state->value;
        }

        if ($state instanceof UnitEnum) {
            return $state->name;
        }

        return $state === null ? null : (string) $state;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $fromStates
     * @return array<string, mixed>
     */
    private function withFromStates(array $payload, array $fromStates): array
    {
        $payload['_from_states'] = $fromStates;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stateAttributes(ScholarshipApplicationStatus $status, array $overrides = []): array
    {
        $attributes = [
            'application_state' => $this->applicationStateFor($status),
            'workflow_state' => $status->workflowState(),
            'workflow_stage' => $status->workflowStage(),
            'approval_state' => $status->approvalState(),
            'payment_state' => $this->paymentStateFor($status),
            'returned_at' => $status->approvalState() === ApprovalState::ReturnedForCorrection->value ? now() : null,
            'rejected_at' => $status->approvalState() === ApprovalState::Rejected->value ? now() : null,
            'completed_at' => $status->isCompleted() ? now() : null,
        ];

        return array_merge($attributes, $overrides);
    }

    private function applicationStateFor(ScholarshipApplicationStatus $status): string
    {
        if ($status->isCompleted()) {
            return ApplicationState::Completed->value;
        }

        if ($status->approvalState() === ApprovalState::Rejected->value) {
            return ApplicationState::Rejected->value;
        }

        if ($status->approvalState() === ApprovalState::ReturnedForCorrection->value) {
            return ApplicationState::ReturnedForCorrection->value;
        }

        return ApplicationState::InWorkflow->value;
    }

    private function paymentStateFor(ScholarshipApplicationStatus $status): string
    {
        return match (true) {
            $status === ScholarshipApplicationStatus::PaymentBatchSubmitted => PaymentState::BeneficiaryPaymentSubmitted->value,
            $status->isPaymentFailed() => PaymentState::BeneficiaryPaymentFailed->value,
            $status->isCompleted() => PaymentState::BeneficiaryPaymentSuccess->value,
            in_array($status, [
                ScholarshipApplicationStatus::RecommendedForPayment,
                ScholarshipApplicationStatus::RecommendedForPaymentViaCCF,
                ScholarshipApplicationStatus::FinalApplicationForPayment,
            ], true) => PaymentState::BeneficiaryPaymentPending->value,
            default => PaymentState::WalletSuccess->value,
        };
    }

    private function recordPaymentAttempt(
        ScholarshipApplication $application,
        ScholarshipWalletTransaction $transaction,
        PaymentAttemptState $state,
        User $user,
        array $response = [],
    ): void {
        $existing = ScholarshipPaymentAttempt::query()
            ->where('wallet_transaction_id', $transaction->id)
            ->first();
        $attemptNumber = $existing instanceof ScholarshipPaymentAttempt ? $existing->attempt_number : (((int) ScholarshipPaymentAttempt::query()
            ->where('scholarship_application_id', $application->id)
            ->where('payment_purpose', 'vle_submission_fee')
            ->max('attempt_number')) ?: 0) + 1;

        ScholarshipPaymentAttempt::query()->updateOrCreate(
            ['wallet_transaction_id' => $transaction->id],
            [
                'scholarship_application_id' => $application->id,
                'payment_purpose' => 'vle_submission_fee',
                'payment_channel' => 'csc_wallet',
                'transaction_number' => $transaction->reference,
                'amount' => $transaction->amount,
                'payment_state' => $state->value,
                'payment_requested_at' => $transaction->created_at,
                'payment_completed_at' => $state === PaymentAttemptState::Completed ? now() : null,
                'failure_reason' => $state === PaymentAttemptState::Failed ? (string) ($response['txn_status_message'] ?? $response['txn_status'] ?? 'Wallet payment failed') : null,
                'attempt_number' => $attemptNumber,
                'request_payload' => $transaction->metadata['request'] ?? null,
                'response_payload' => $response ?: null,
                'created_by' => $user->id,
            ],
        );
    }

    private function recordBeneficiaryPaymentAttempt(
        ScholarshipApplication $application,
        PaymentAttemptState $state,
        User $user,
        ?string $reference = null,
        ?string $failureReason = null,
        array $response = [],
    ): void {
        $attemptNumber = (((int) ScholarshipPaymentAttempt::query()
            ->where('scholarship_application_id', $application->id)
            ->where('payment_purpose', 'scholarship_disbursement')
            ->max('attempt_number')) ?: 0) + 1;

        ScholarshipPaymentAttempt::query()->create([
            'scholarship_application_id' => $application->id,
            'wallet_transaction_id' => null,
            'payment_purpose' => 'scholarship_disbursement',
            'payment_channel' => 'axis_bank',
            'transaction_number' => $reference,
            'amount' => $application->amount,
            'payment_state' => $state->value,
            'payment_requested_at' => in_array($state, [PaymentAttemptState::Submitted, PaymentAttemptState::Processing], true) ? now() : null,
            'payment_completed_at' => in_array($state, [PaymentAttemptState::Completed, PaymentAttemptState::Failed], true) ? now() : null,
            'failure_reason' => $state === PaymentAttemptState::Failed ? $failureReason : null,
            'attempt_number' => $attemptNumber,
            'request_payload' => $state === PaymentAttemptState::Submitted ? $response : null,
            'response_payload' => $state !== PaymentAttemptState::Submitted ? ($response ?: null) : null,
            'created_by' => $user->id,
        ]);
    }

    private function updateLatestPaymentBatchRow(ScholarshipApplication $application, bool $success, ?string $failureReason): void
    {
        $batchApplication = $application->batchApplications()
            ->latest()
            ->first();

        if ($batchApplication === null) {
            return;
        }

        $batchApplication->forceFill([
            'payment_status' => $success ? 'success' : 'failed',
            'payment_failure_reason' => $success ? null : $failureReason,
        ])->save();
    }

    private function notify(ScholarshipApplication $application, User $user, string $subject, string $body): void
    {
        ScholarshipNotification::query()->create([
            'scholarship_application_id' => $application->id,
            'user_id' => $application->applicant_user_id ?: $user->id,
            'subject' => $subject,
            'body' => $body,
            'status' => 'queued',
        ]);
    }

    private function recordWalletEntry(ScholarshipApplication $application, User $user, string $type, int|float $amount, string $reference): void
    {
        ScholarshipWalletTransaction::query()->firstOrCreate(
            ['reference' => $reference],
            [
                'scholarship_application_id' => $application->id,
                'user_id' => $user->id,
                'transaction_type' => $type,
                'amount' => $amount,
                'status' => 'posted',
                'metadata' => ['source_wallet_import_found' => false],
            ],
        );
    }

    private function applicationNumber(ScholarshipApplication $application): string
    {
        $session = $application->scholarshipSession()->value('name')
            ?? $application->academicSession()->value('name')
            ?? now()->format('Y');

        return 'SCH-'.str_replace('-', '', $session).'-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT);
    }

    private function calculateAmount(int $schemeId, string $class, int $yearOfStudy): int
    {
        if ($schemeId === 1) {
            return $class === '12' ? 3000 : ($class === '10' ? 2500 : 0);
        }

        if ($schemeId === 2) {
            return $class === '12' ? 25000 : ($class === '10' ? 15000 : 0);
        }

        if ($schemeId === 3) {
            return $yearOfStudy <= 1 ? 10000 : 5000;
        }

        if ($schemeId === 4) {
            if ($yearOfStudy <= 1) {
                return 5000;
            }

            return $yearOfStudy === 2 ? 4000 : 3000;
        }

        return 0;
    }

    private function inputValue(array $data, string $key, ?ScholarshipApplication $application, mixed $default = null): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        if ($application === null) {
            return $default;
        }

        return $application->getAttribute($key) ?? $default;
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->lower()->replaceMatches('/[^a-z0-9]/', '')->toString();
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function digitsOrNull(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }
}
