<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Client\ClientLinkService;
use App\Services\Client\IssueViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IssueController extends Controller
{
    public function __construct(
        private readonly IssueViewService $issueViewService,
        private readonly ClientLinkService $linkService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $hasIssue = $package->conditionChecks()->whereHas('issueReport')->exists();

        if (! $hasIssue) {
            return response()->json([
                'success' => true,
                'redirect_url' => $this->linkService->tracking($package),
            ]);
        }

        $view = $this->issueViewService->build($package);

        return response()->json([
            'success' => true,
            'data' => $view->toArray(),
        ]);
    }
}
