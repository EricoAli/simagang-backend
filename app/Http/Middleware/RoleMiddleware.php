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
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @param mixed ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Jika tidak login
        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Jika role-nya sys, langsung lolos (testing only)
        if ($user->role === 'sys') {
            return $next($request);
        }

        // Super Admin mewarisi seluruh route yang mengizinkan Admin,
        // tetapi tidak otomatis mendapat route khusus Mahasiswa/Mitra.
        if ($user->role === 'super_admin' && in_array('admin', $roles, true)) {
            return $next($request);
        }

        // Periksa apakah role user sesuai salah satu role yang diberikan
        if (!in_array($user->role, $roles)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
