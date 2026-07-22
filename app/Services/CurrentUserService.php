<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class CurrentUserService
{
    public function __construct(
        private readonly Request $request,
        private readonly RoleService $roles,
        private readonly PermissionService $permissions,
    ) {}

    public function user(): ?User
    {
        $user = $this->request->user();

        return $user instanceof User ? $user : null;
    }

    public function roleKey(): int|string|null
    {
        return $this->roles->key($this->user());
    }

    public function roleName(): string
    {
        return $this->roles->name($this->user());
    }

    public function isVle(): bool
    {
        return $this->roles->isVle($this->user());
    }

    /**
     * @return Collection<int, int>
     */
    public function permissionIds(): Collection
    {
        $user = $this->user();

        return $user ? $this->permissions->idsFor($user) : collect();
    }
}
