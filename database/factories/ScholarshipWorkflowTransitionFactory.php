<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Scholarship\Enums\ApplicationState;
use App\Domains\Scholarship\Enums\ApprovalState;
use App\Domains\Scholarship\Enums\PaymentState;
use App\Domains\Scholarship\Enums\WorkflowStage;
use App\Domains\Scholarship\Enums\WorkflowState;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipWorkflowTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScholarshipWorkflowTransition>
 */
final class ScholarshipWorkflowTransitionFactory extends Factory
{
    protected $model = ScholarshipWorkflowTransition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scholarship_application_id' => ScholarshipApplication::factory()->submitted(),
            'from_application_state' => ApplicationState::Created->value,
            'to_application_state' => ApplicationState::InWorkflow->value,
            'from_workflow_state' => WorkflowState::PendingAtVle->value,
            'to_workflow_state' => WorkflowState::PendingSamiti->value,
            'from_workflow_stage' => WorkflowStage::Vle->value,
            'to_workflow_stage' => WorkflowStage::Samiti->value,
            'from_payment_state' => PaymentState::WalletPending->value,
            'to_payment_state' => PaymentState::WalletSuccess->value,
            'from_approval_state' => ApprovalState::Pending->value,
            'to_approval_state' => ApprovalState::Pending->value,
            'action' => 'submitted',
            'remarks' => 'Application submitted',
            'acted_by' => User::factory(),
            'acted_by_role' => 'VLE',
            'acted_at' => now(),
            'payload' => null,
        ];
    }
}
