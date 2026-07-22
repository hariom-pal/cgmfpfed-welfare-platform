<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

final class SessionService
{
    public function __construct(private readonly RoleService $roles) {}

    public function storeLegacyKeys(Request $request, User $user): void
    {
        $request->session()->put([
            'USER_ID' => $user->id,
            'NAME' => $user->name,
            'EMAIL' => $user->email,
            'MOBILE' => $user->mobile,
            'USER_TYPE' => $this->roles->key($user),
        ]);

        if ($this->roles->isVle($user) && $user->csc_id !== null) {
            $request->session()->put('CSC_ID', $user->csc_id);
        }
    }

    public function clearLegacyKeys(Request $request): void
    {
        $request->session()->forget([
            'USER_ID',
            'NAME',
            'EMAIL',
            'MOBILE',
            'USER_TYPE',
            'CSC_ID',
            'connect_state',
            'PAYMENT_BATCH_ID',
        ]);
    }
}
