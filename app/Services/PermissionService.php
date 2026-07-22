<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RolePriviledge;
use App\Models\User;
use Illuminate\Support\Collection;

final class PermissionService
{
    public function __construct(private readonly RoleService $roles) {}

    public function has(User $user, int|string $permission): bool
    {
        $permissionId = (int) $permission;

        if ($this->roles->isVle($user)) {
            return in_array($permissionId, config('legacy_authorization.vle_effective_permissions', []), true);
        }

        if ($user->user_type === null) {
            return false;
        }

        return RolePriviledge::query()
            ->where('role_id', (int) $user->user_type)
            ->where('permission_id', $permissionId)
            ->exists();
    }

    /**
     * @param  iterable<int|string>|int|string  $permissions
     */
    public function hasAny(User $user, iterable|int|string $permissions): bool
    {
        foreach (is_iterable($permissions) ? $permissions : [$permissions] as $permission) {
            if ($this->has($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function can(User $user, string $ability): bool
    {
        $definition = config('legacy_authorization.abilities', [])[$ability] ?? null;

        if (! is_array($definition)) {
            return false;
        }

        $roleRule = $definition['roles'] ?? null;
        if ($roleRule === '*') {
            return true;
        }

        $roleMatches = false;
        if (is_array($roleRule)) {
            $roleMatches = in_array($this->roles->key($user), $roleRule, true);
        }

        $permissionRule = $definition['permissions'] ?? [];
        $permissionMatches = $permissionRule !== [] && $this->hasAny($user, $permissionRule);

        return $roleMatches || $permissionMatches;
    }

    /**
     * @return Collection<int, int>
     */
    public function idsFor(User $user): Collection
    {
        if ($this->roles->isVle($user)) {
            return collect(config('legacy_authorization.vle_effective_permissions', []))->map(fn (mixed $id): int => (int) $id);
        }

        if ($user->user_type === null) {
            return collect();
        }

        return RolePriviledge::query()
            ->where('role_id', (int) $user->user_type)
            ->pluck('permission_id')
            ->map(fn (mixed $id): int => (int) $id);
    }
}
