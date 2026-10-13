<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\Client\ClientScreen;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Client\ClientLinkService;
use App\Services\Client\ClientScreenResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EntryController extends Controller
{
    public function __construct(
        private readonly ClientScreenResolver $screenResolver,
        private readonly ClientLinkService $linkService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $screen = $this->screenResolver->resolve($package);

        $url = match ($screen) {
            ClientScreen::SCHEDULE => $this->linkService->schedule($package),
            ClientScreen::ISSUE => $this->linkService->issue($package),
            ClientScreen::TRACKING => $this->linkService->tracking($package),
        };

        return response()->json([
            'screen' => $screen->value,
            'redirect_url' => $url,
        ]);
    }
}
