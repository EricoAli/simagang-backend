<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMasterDataManager
{
    /**
     * Mitra and Dosbing are shared master catalogs. Only administrative roles
     * may mutate them; other role access to their read endpoints is unchanged.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $isSuperAdmin = $user?->role === 'super_admin';
        $isAdminWithProdi = $user?->role === 'admin'
            && (bool) optional($user->admin)->prodi_id;

        if (!$isSuperAdmin && !$isAdminWithProdi) {
            abort(403, 'Hanya Super Admin dan Admin Prodi yang dapat mengelola master data.');
        }

        return $next($request);
    }
}
