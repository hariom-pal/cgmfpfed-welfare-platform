<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\PermissionService;

final class ScholarshipApplicationPolicy
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly DataScopeService $scope,
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
        return $this->permissions->can($user, 'applications.update')
            && $this->scope->canViewScholarshipApplication($user, $application);
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
