<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureLegacyVisitorAccess
{
    public function __construct(private readonly RoleService $roles) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $this->roles->isVle($user)) {
            $routeName = (string) $request->route()?->getName();
            $allowed = $routeName === 'logout';

            foreach (config('legacy_authorization.vle_route_prefixes', []) as $prefix) {
                if ($routeName === $prefix || str_starts_with($routeName, (string) $prefix)) {
                    $allowed = true;
                    break;
                }
            }

            abort_unless($allowed, 403);
        }

        return $next($request);
    }
}
