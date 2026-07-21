<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Services;

use App\Contracts\Services\AadhaarServiceInterface;
use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationAudit;
use App\Models\ScholarshipApplicationDocument;
use App\Models\ScholarshipNotification;
use App\Models\ScholarshipTendupattaCollection;
use App\Models\ScholarshipWalletTransaction;
use App\Models\ScholarshipWorkflowBatch;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ScholarshipService extends BaseService implements ScholarshipServiceInterface
{
    public function __construct(
        private readonly AadhaarServiceInterface $aadhaarService,
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
            $fromStatus = (int) $application->status;
            $application->fill([
                'application_number' => $application->application_number ?: $this->applicationNumber($application),
                'status' => $status->value,
                'status_label' => $status->label(),
                'current_stage' => $status->stage(),
                'is_draft' => false,
                'submitted_at' => now(),
                'submitted_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            $this->recordWalletEntry($application, $user, 'application_submission', 0, 'SUBMIT-'.$application->id);
            $this->audit($application, 'submitted', $fromStatus, $status->stage(), 'Application finally submitted', $user);
            $this->notify($application, $user, 'Scholarship application submitted', $status->label());

            return $application->refresh();
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
            ScholarshipApplicationStatus::PaymentFailed->value,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Only returned or payment-failed applications can be resubmitted.']);
        }

        return DB::transaction(function () use ($application, $data, $user): ScholarshipApplication {
            $payload = $this->normalizeApplicationData($data, $user, $application);
            $fromStatus = (int) $application->status;
            $status = $fromStatus === ScholarshipApplicationStatus::PermanentlyRejectedByAccounts->value
                ? ScholarshipApplicationStatus::AccountDetailsUpdatedByHQ
                : ScholarshipApplicationStatus::Resubmitted;

            $application->fill($payload + [
                'status' => $status->value,
                'status_label' => $status->label(),
                'current_stage' => $status->stage(),
                'is_draft' => false,
                'updated_by' => $user->id,
            ])->save();

            $this->validateForSubmit($application->refresh());
            $this->syncChildren($application, $data, false, $user);
            $this->audit($application, 'resubmitted', $fromStatus, $status->stage(), 'Application resubmitted after return', $user, $data);
            $this->notify($application, $user, 'Scholarship application resubmitted', $status->label());

            return $application->refresh();
        });
    }

    public function transition(ScholarshipApplication $application, string $action, ?string $remarks, User $user): ScholarshipApplication
    {
        return DB::transaction(function () use ($application, $action, $remarks, $user): ScholarshipApplication {
            $status = $this->nextStatus($application, $action);
            $fromStatus = (int) $application->status;

            $updates = [
                'status' => $status->value,
                'status_label' => $status->label(),
                'current_stage' => $status->stage(),
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

            $application->fill($updates)->save();
            $this->audit($application, $action, $fromStatus, $status->stage(), $remarks ?: $status->label(), $user);
            $this->notify($application, $user, 'Scholarship workflow updated', $status->label());

            return $application->refresh();
        });
    }

    public function createIcBatch(array $applicationIds, User $user, ?string $momFilePath = null, ?string $remarks = null): ScholarshipWorkflowBatch
    {
        if ($momFilePath === null || trim($momFilePath) === '') {
            throw ValidationException::withMessages(['mom_file_path' => 'IC batch MoM document is mandatory.']);
        }

        return $this->createBatch('IC', $applicationIds, ScholarshipApplicationStatus::RecommendedBySamiti, $user, $momFilePath, $remarks);
    }

    public function createPaymentBatch(array $applicationIds, User $user, ?string $remarks = null): ScholarshipWorkflowBatch
    {
        return $this->createBatch('PAYMENT', $applicationIds, ScholarshipApplicationStatus::FinalApplicationForPayment, $user, null, $remarks);
    }

    public function recordPaymentResult(ScholarshipApplication $application, bool $success, ?string $reference, ?string $failureReason, User $user): ScholarshipApplication
    {
        return DB::transaction(function () use ($application, $success, $reference, $failureReason, $user): ScholarshipApplication {
            $status = $success ? ScholarshipApplicationStatus::PaymentCompleted : ScholarshipApplicationStatus::PaymentFailed;
            $fromStatus = (int) $application->status;

            $application->fill([
                'status' => $status->value,
                'status_label' => $status->label(),
                'current_stage' => $status->stage(),
                'payment_status' => $success ? 'success' : 'failed',
                'payment_reference_id' => $reference,
                'payment_failure_reason' => $success ? null : $failureReason,
                'paid_at' => $success ? now() : null,
                'updated_by' => $user->id,
            ])->save();

            $this->audit($application, $success ? 'payment_success' : 'payment_failed', $fromStatus, $status->stage(), $failureReason ?: $status->label(), $user);

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
        $academicSessionId = (int) $this->inputValue($data, 'academic_session_id', $application, 0);

        $duplicateSession = ScholarshipApplication::query()
            ->where('student_aadhaar', $studentAadhaar)
            ->where('academic_session_id', $academicSessionId)
            ->when($application, fn ($query) => $query->whereKeyNot($application->id))
            ->exists();

        if ($duplicateSession) {
            throw ValidationException::withMessages([
                'student_aadhaar' => 'One Student Aadhaar can have only one scholarship application in one Academic Session.',
            ]);
        }

        return [
            'academic_session_id' => $academicSessionId,
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
            'class' => $class,
            'school_college_name' => $this->inputValue($data, 'school_college_name', $application),
            'board_university' => $this->inputValue($data, 'board_university', $application),
            'roll_number' => $this->inputValue($data, 'roll_number', $application),
            'marks_obtained' => $marksObtained,
            'maximum_marks' => $maximumMarks,
            'percentage' => $percentage,
            'course_name' => $this->inputValue($data, 'course_name', $application),
            'institution_name' => $this->inputValue($data, 'institution_name', $application),
            'admission_year' => $this->nullableInt($this->inputValue($data, 'admission_year', $application)),
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

        $duplicateSession = ScholarshipApplication::query()
            ->where('student_aadhaar', $application->student_aadhaar)
            ->where('academic_session_id', $application->academic_session_id)
            ->whereKeyNot($application->id)
            ->exists();

        if ($duplicateSession) {
            $errors['student_aadhaar'] = 'One Student Aadhaar can have only one scholarship application in one Academic Session.';
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
            'RecommendedForPayment:recommend' => ScholarshipApplicationStatus::FinalApplicationForPayment,
            'FinalApplicationForPayment:submit_payment_batch' => ScholarshipApplicationStatus::PaymentBatchSubmitted,
            'PaymentFailed:recommend' => ScholarshipApplicationStatus::FinalApplicationForPayment,
            default => null,
        };

        if (! $status instanceof ScholarshipApplicationStatus) {
            throw ValidationException::withMessages(['action' => 'This workflow action is not valid for the current application status.']);
        }

        return $status;
    }

    private function createBatch(string $type, array $applicationIds, ScholarshipApplicationStatus $requiredStatus, User $user, ?string $momFilePath, ?string $remarks): ScholarshipWorkflowBatch
    {
        return DB::transaction(function () use ($type, $applicationIds, $requiredStatus, $user, $momFilePath, $remarks): ScholarshipWorkflowBatch {
            $applications = ScholarshipApplication::query()
                ->whereIn('id', array_unique(array_map('intval', $applicationIds)))
                ->where('status', $requiredStatus->value)
                ->get();

            if ($applications->count() !== count(array_unique($applicationIds))) {
                throw ValidationException::withMessages(['application_ids' => 'All applications must be in the required workflow status for this batch.']);
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
                    $this->transition($application, 'submit_payment_batch', $remarks, $user);
                } else {
                    $this->audit($application, 'ic_batch_submitted', (int) $application->status, $application->current_stage, $remarks ?: 'IC batch submitted with MoM', $user, ['batch_id' => $batch->id]);
                }
            }

            return $batch->refresh();
        });
    }

    private function syncChildren(ScholarshipApplication $application, array $data, bool $verified, User $user): void
    {
        foreach ($data['documents'] ?? [] as $documentType => $document) {
            if (! is_array($document)) {
                $document = ['file_path' => $document];
            }

            ScholarshipApplicationDocument::query()->updateOrCreate(
                ['scholarship_application_id' => $application->id, 'document_type' => (string) $documentType],
                [
                    'file_path' => $document['file_path'] ?? null,
                    'source' => strtoupper((string) ($document['source'] ?? 'MANUAL')),
                    'is_verified' => $verified,
                    'verified_by' => $verified ? $user->id : null,
                    'verified_at' => $verified ? now() : null,
                    'remarks' => $document['remarks'] ?? null,
                ],
            );
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

    private function audit(ScholarshipApplication $application, string $action, ?int $fromStatus, ?string $stage, ?string $remarks, User $user, array $payload = []): void
    {
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
                'metadata' => ['legacy_wallet_table_found' => false],
            ],
        );
    }

    private function applicationNumber(ScholarshipApplication $application): string
    {
        $session = $application->academicSession()->value('name') ?? now()->format('Y');

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
}
