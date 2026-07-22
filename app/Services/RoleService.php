<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class RoleService
{
    public function key(?User $user): int|string|null
    {
        if ($user === null || $user->user_type === null) {
            return null;
        }

        return $this->isVle($user) ? 'VLE' : (int) $user->user_type;
    }

    public function isVle(?User $user): bool
    {
        return $user !== null && (int) $user->user_type === (int) config('csc.vle_role_id');
    }

    public function isSuperAdmin(?User $user): bool
    {
        return $this->key($user) === 1;
    }

    public function name(?User $user): string
    {
        $key = $this->key($user);

        return (string) (config('legacy_authorization.roles')[$key] ?? 'Unknown');
    }
}
