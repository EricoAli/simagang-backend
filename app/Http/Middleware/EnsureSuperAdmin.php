<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'super_admin') {
            abort(403, 'Hanya Super Admin yang dapat mengelola master periode.');
        }

        return $next($request);
    }
}
