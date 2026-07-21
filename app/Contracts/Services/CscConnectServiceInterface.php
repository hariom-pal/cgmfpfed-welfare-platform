<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Http\Request;

interface CscConnectServiceInterface
{
    public function authorizationUrl(Request $request): string;

    public function authenticateCallback(Request $request): User;
}
