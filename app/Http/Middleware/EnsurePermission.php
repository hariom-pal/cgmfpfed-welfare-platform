<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $permissionIds = collect($permissions)
            ->flatMap(fn (string $permission): array => preg_split('/[|,]/', $permission) ?: [])
            ->filter()
            ->map(fn (string $permission): int => (int) $permission)
            ->all();

        abort_if($user === null || ! $user->hasAnyPermission($permissionIds), 403);

        return $next($request);
    }
}
