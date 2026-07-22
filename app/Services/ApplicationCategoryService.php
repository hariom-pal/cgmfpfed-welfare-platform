<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Scholarship\Enums\ApplicationState;
use App\Domains\Scholarship\Enums\ApprovalState;
use App\Domains\Scholarship\Enums\PaymentState;
use App\Domains\Scholarship\Enums\SubmissionState;
use App\Domains\Scholarship\Enums\WorkflowState;
use App\Models\ScholarshipApplication;
use Illuminate\Database\Eloquent\Builder;

final class ApplicationCategoryService
{
    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return [
            'pending-at-vle' => 'Pending at VLE',
            'under-process' => 'Under Process',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'rejected' => 'Rejected',
        ];
    }

    /**
     * @param  Builder<ScholarshipApplication>  $query
     * @return Builder<ScholarshipApplication>
     */
    public function apply(Builder $query, ?string $category): Builder
    {
        return match ($this->normalize($category)) {
            'pending-at-vle' => $query->where(function (Builder $builder): void {
                $builder
                    ->where('application_state', ApplicationState::Created->value)
                    ->orWhere('submission_state', SubmissionState::WalletPending->value)
                    ->orWhere('workflow_state', WorkflowState::PendingAtVle->value);
            }),
            'under-process' => $query->where('application_state', ApplicationState::InWorkflow->value),
            'completed' => $query->where('application_state', ApplicationState::Completed->value),
            'failed' => $query->where('payment_state', PaymentState::BeneficiaryPaymentFailed->value),
            'rejected' => $query->where('approval_state', ApprovalState::Rejected->value),
            default => $query,
        };
    }

    public function normalize(?string $category): ?string
    {
        return match ($category) {
            'pending', 'pending_at_vle', 'pending-at-vle' => 'pending-at-vle',
            'processing', 'underprocess', 'under-process' => 'under-process',
            'complete', 'completed' => 'completed',
            'payment_failed', 'failed' => 'failed',
            'reject', 'rejected' => 'rejected',
            default => null,
        };
    }
}
