<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Model;

final class MasterPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function manage(User $user, Model $master): bool
    {
        return $this->permissions->can($user, 'masters.manage');
    }
}
