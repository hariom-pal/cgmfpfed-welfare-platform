<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\PermissionService;
use App\Services\RoleService;

final class ScholarshipApplicationPolicy
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly DataScopeService $scope,
        private readonly RoleService $roles,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'applications.view');
    }

    public function view(User $user, ScholarshipApplication $application): bool
    {
        return $this->permissions->can($user, 'applications.view')
            && $this->scope->canViewScholarshipApplication($user, $application);
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'applications.create');
    }

    public function update(User $user, ScholarshipApplication $application): bool
    {
        if (! $this->roles->isVle($user) || ! $this->scope->canViewScholarshipApplication($user, $application)) {
            return false;
        }

        if ((int) $application->applicant_user_id !== (int) $user->id) {
            return false;
        }

        if ($application->is_draft || $application->submitted_at === null) {
            return true;
        }

        return $application->status_enum?->isEditableByVleAfterReturn() === true
            && filled($application->metadata['editable_documents'] ?? null);
    }

    public function submit(User $user, ScholarshipApplication $application): bool
    {
        return $this->permissions->can($user, 'applications.submit')
            && $this->scope->canViewScholarshipApplication($user, $application);
    }

    public function viewDocument(User $user, ScholarshipApplication $application): bool
    {
        return $this->permissions->can($user, 'applications.documents.view')
            && $this->scope->canViewScholarshipApplication($user, $application);
    }

    public function delete(User $user, ScholarshipApplication $application): bool
    {
        if (! $this->roles->isVle($user) || ! $this->scope->canViewScholarshipApplication($user, $application)) {
            return false;
        }

        if ((int) $application->applicant_user_id !== (int) $user->id) {
            return false;
        }

        return in_array((int) $application->status, [
            ScholarshipApplicationStatus::Pending->value,
            ScholarshipApplicationStatus::Resubmitted->value,
            ScholarshipApplicationStatus::PermanentlyRejectedBySamiti->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByIC->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByCCF->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByDistrictUnion->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByHQ->value,
            ScholarshipApplicationStatus::PermanentlyRejectedByAccounts->value,
        ], true);
    }

    /**
     * Stage-aware workflow authorization: `workflow.action` (config-driven) only proves the
     * user's role is *some* workflow participant. This additionally enforces which role may act
     * at the application's *current* stage, matching each legacy role's exact responsibility
     * (Samiti/IC/District Union/HQ per-stage review, Account's forward/remove, HQ's failed-payment
     * retry) instead of letting any workflow role act on any application at any stage.
     */
    public function act(User $user, ScholarshipApplication $application, string $action): bool
    {
        if (! $this->permissions->can($user, 'workflow.action') || ! $this->scope->canViewScholarshipApplication($user, $application)) {
            return false;
        }

        $role = $this->roles->key($user);
        $status = ScholarshipApplicationStatus::tryFrom((int) $application->status);
        $reviewActions = ['recommend', 'return', 'reject'];

        return match (true) {
            in_array($status, [ScholarshipApplicationStatus::Pending, ScholarshipApplicationStatus::Resubmitted], true) => $role === 3 && in_array($action, $reviewActions, true),
            $status === ScholarshipApplicationStatus::RecommendedBySamiti => $role === 4 && in_array($action, $reviewActions, true),
            $status === ScholarshipApplicationStatus::RecommendedByIC => $role === 2 && in_array($action, $reviewActions, true),
            $status === ScholarshipApplicationStatus::RecommendedByDistrictUnion => $role === 1 && in_array($action, $reviewActions, true),
            $status === ScholarshipApplicationStatus::RecommendedForPayment => $role === 6 && $action === 'forward',
            $status === ScholarshipApplicationStatus::FinalApplicationForPayment => $role === 6 && $action === 'remove',
            $status === ScholarshipApplicationStatus::PaymentFailed => in_array($role, [1, 6], true) && $action === 'retry',
            $status === ScholarshipApplicationStatus::AccountDetailsUpdatedByHQ => $role === 1 && $action === 'recommend',
            default => false,
        };
    }

    public function recordPaymentResult(User $user, ScholarshipApplication $application): bool
    {
        if (! $this->permissions->can($user, 'workflow.action') || ! $this->scope->canViewScholarshipApplication($user, $application)) {
            return false;
        }

        return $this->roles->key($user) === 1
            && (int) $application->status === ScholarshipApplicationStatus::PaymentBatchSubmitted->value;
    }
}
