<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforcement server-side untuk konteks (admin/tenant). Menu tersembunyi bukan
 * authorization; setiap route internal tetap ditolak 403 bila role tidak cocok.
 * Kontrak role penuh (policy + tenant membership) menyusul Modul 4–5.
 */
final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        // Guest ditangani middleware auth; di sini fokus pada role/status.
        if ($user === null || ! $user->hasRole($role)) {
            abort(403);
        }

        return $next($request);
    }
}
