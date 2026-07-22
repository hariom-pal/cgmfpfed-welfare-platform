<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
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
                    ->where('is_draft', true)
                    ->orWhereNull('submitted_at')
                    ->orWhereNull('wallet_paid_at')
                    ->orWhereIn('status', ScholarshipApplicationStatus::pendingAtVleValues());
            }),
            'under-process' => $query->where('is_draft', false)->whereIn('status', ScholarshipApplicationStatus::underProcessValues()),
            'completed' => $query->where('is_draft', false)->whereIn('status', ScholarshipApplicationStatus::completedValues()),
            'failed' => $query->whereIn('status', ScholarshipApplicationStatus::failedValues()),
            'rejected' => $query->whereIn('status', ScholarshipApplicationStatus::rejectedValues()),
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
