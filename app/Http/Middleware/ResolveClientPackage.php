<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Client\ClientAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveClientPackage
{
    public function __construct(
        private readonly ClientAccessService $accessService
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');

        $package = $this->accessService->resolve($token, $request->ip());

        $request->attributes->set('client_package', $package);
        $request->route()->setParameter('token', $package);
        $request->route()->setParameter('package', $package);

        return $next($request);
    }
}
