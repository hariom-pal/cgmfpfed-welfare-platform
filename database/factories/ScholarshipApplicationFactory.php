<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Scholarship\Enums\ApplicationState;
use App\Domains\Scholarship\Enums\ApprovalState;
use App\Domains\Scholarship\Enums\PaymentState;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Domains\Scholarship\Enums\SubmissionState;
use App\Domains\Scholarship\Enums\WorkflowStage;
use App\Domains\Scholarship\Enums\WorkflowState;
use App\Models\AcademicSession;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScholarshipApplication>
 */
final class ScholarshipApplicationFactory extends Factory
{
    protected $model = ScholarshipApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $studentName = $this->faker->name();

        return [
            'uuid' => (string) Str::uuid(),
            'application_number' => null,
            'applicant_user_id' => User::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'scheme_id' => Scheme::factory(),
            'status' => ScholarshipApplicationStatus::Pending->value,
            'status_label' => 'Draft',
            'current_stage' => 'draft',
            'application_state' => ApplicationState::Created->value,
            'submission_state' => SubmissionState::Draft->value,
            'workflow_state' => WorkflowState::PendingAtVle->value,
            'workflow_stage' => WorkflowStage::Vle->value,
            'approval_state' => ApprovalState::Pending->value,
            'payment_state' => PaymentState::WalletNotStarted->value,
            'is_draft' => true,
            'student_aadhaar' => $this->faker->unique()->numerify('############'),
            'aadhaar_verified_student_name' => $studentName,
            'student_name' => $studentName,
            'amount' => 0,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function walletPending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'submission_state' => SubmissionState::WalletPending->value,
            'payment_state' => PaymentState::WalletPending->value,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'application_number' => 'SCH-'.$this->faker->unique()->numerify('########'),
            'status_label' => ScholarshipApplicationStatus::Pending->label(),
            'current_stage' => ScholarshipApplicationStatus::Pending->stage(),
            'application_state' => ApplicationState::InWorkflow->value,
            'submission_state' => SubmissionState::Submitted->value,
            'workflow_state' => WorkflowState::PendingSamiti->value,
            'workflow_stage' => WorkflowStage::Samiti->value,
            'approval_state' => ApprovalState::Pending->value,
            'payment_state' => PaymentState::WalletSuccess->value,
            'is_draft' => false,
            'submitted_at' => now(),
            'entered_workflow_at' => now(),
            'wallet_paid_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->submitted()->state(fn (array $attributes): array => [
            'status' => ScholarshipApplicationStatus::PaymentCompleted->value,
            'status_label' => ScholarshipApplicationStatus::PaymentCompleted->label(),
            'current_stage' => ScholarshipApplicationStatus::PaymentCompleted->stage(),
            'application_state' => ApplicationState::Completed->value,
            'workflow_state' => WorkflowState::PaymentCompleted->value,
            'workflow_stage' => WorkflowStage::Completed->value,
            'approval_state' => ApprovalState::Approved->value,
            'payment_state' => PaymentState::BeneficiaryPaymentSuccess->value,
            'completed_at' => now(),
            'paid_at' => now(),
        ]);
    }
}
