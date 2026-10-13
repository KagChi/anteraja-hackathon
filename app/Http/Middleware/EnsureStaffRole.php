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
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(401, 'Unauthenticated.');
            }

            return redirect()->guest('/login');
        }

        $expectedRole = StaffRole::tryFrom($role) ?? StaffRole::ADMIN;

        if ($user->role !== $expectedRole || ! $user->is_active) {
            abort(403, 'Akses ditolak: Hanya staf aktif yang memiliki otorisasi.');
        }

        return $next($request);
    }
}
