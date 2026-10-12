<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  mixed  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Jika tidak login
        if (! $user) {
            abort(403, 'Unauthorized');
        }

        // Cek status keaktifan akun
        if (! $user->is_active) {
            abort(403, 'Akun anda telah dinonaktifkan.');
        }

        // Periksa apakah role user sesuai salah satu role yang diberikan
        if (! in_array($user->role, $roles)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
