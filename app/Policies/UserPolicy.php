<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionService;
use App\Services\RoleService;

final class UserPolicy
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly RoleService $roles,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'users.view');
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'users.create');
    }

    public function update(User $user, User $target): bool
    {
        if ($this->roles->isSuperAdmin($target) || $this->roles->isVle($target)) {
            return false;
        }

        return $this->permissions->can($user, 'users.update');
    }
}
