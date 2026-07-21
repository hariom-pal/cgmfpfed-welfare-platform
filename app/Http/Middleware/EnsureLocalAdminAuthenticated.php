<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class EnsureLocalAdminAuthenticated
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->session()->get('local_admin_authenticated') !== true) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
