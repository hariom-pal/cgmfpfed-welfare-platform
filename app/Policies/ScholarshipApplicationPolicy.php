<?php

declare(strict_types=1);

namespace App\Policies;

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
}
