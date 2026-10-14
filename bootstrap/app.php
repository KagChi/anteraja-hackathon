<?php

use App\Http\Middleware\ClientSecurityHeaders;
use App\Http\Middleware\EnsureBuyer;
use App\Http\Middleware\EnsureStaffRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveClientPackage;
use App\Http\Middleware\ResolveOwnedPackage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [HandleInertiaRequests::class]);
        // The admin console drives the JSON API from the browser with a session
        // cookie, so the group needs the same CSRF protection the web group has.
        // A future bearer-token consumer (the courier/client mobile apps) is not
        // cookie-authenticated and must be added to ValidateCsrfToken's except
        // list rather than being sent through this.
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ValidateCsrfToken::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/client/*',
            'api/guest/*',
        ]);
        $middleware->alias([
            'staff.role' => EnsureStaffRole::class,
            'client.headers' => ClientSecurityHeaders::class,
            'client.package' => ResolveClientPackage::class,
            'client.buyer' => EnsureBuyer::class,
            'client.owned' => ResolveOwnedPackage::class,
            'force.json' => ForceJsonResponse::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }
        });

        $exceptions->render(function (MissingAbilityException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak: kemampuan token tidak sesuai.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        });
    })
    ->create();
