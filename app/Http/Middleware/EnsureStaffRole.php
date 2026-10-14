<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\StaffRole;
use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureStaffRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role = 'ADMIN'): Response
    {
        $user = $request->user();

        if (! $user instanceof Staff) {
            // Only the JSON API answers with a 401. Anything else is a browser
            // navigation, and Inertia page loads send X-Requested-With too, so
            // they have to be redirected rather than aborted into an error modal.
            if ($request->is('api/*')) {
                abort(401, 'Unauthenticated.');
            }

            return redirect()->guest(route('login'));
        }

        $expectedRole = StaffRole::tryFrom($role) ?? StaffRole::ADMIN;

        if ($user->role !== $expectedRole || ! $user->is_active) {
            abort(403, 'Akses ditolak: Hanya staf aktif yang memiliki otorisasi.');
        }

        return $next($request);
    }
}
