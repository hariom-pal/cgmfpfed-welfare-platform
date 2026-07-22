<?php

declare(strict_types=1);

namespace App\Services;

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
                    ->where('application_state', 'created')
                    ->orWhere('submission_state', 'wallet_pending')
                    ->orWhere('workflow_state', 'pending_at_vle');
            }),
            'under-process' => $query->where('application_state', 'in_workflow'),
            'completed' => $query->where('application_state', 'completed'),
            'failed' => $query->where('payment_state', 'beneficiary_payment_failed'),
            'rejected' => $query->where('approval_state', 'rejected'),
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
