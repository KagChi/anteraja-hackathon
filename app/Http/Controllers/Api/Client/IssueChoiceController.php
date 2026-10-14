<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Actions\Client\RecordIssueChoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IssueChoiceRequest;
use App\Models\Package;
use App\Services\Client\ClientLinkService;
use Illuminate\Http\JsonResponse;

final class IssueChoiceController extends Controller
{
    public function __invoke(
        IssueChoiceRequest $request,
        RecordIssueChoice $action,
        ClientLinkService $linkService,
    ): JsonResponse {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $result = $action->execute($package, $request->toChoice());
        $replacement = $result['replacement'];

        return response()->json([
            'success' => true,
            'message' => 'Pilihan penyelesaian kendala berhasil dicatat.',
            'data' => [
                'choice' => $result['choice']->value,
                'choice_label' => $result['choice']->label(),
                'already_recorded' => $result['already_recorded'],
                'replacement_tracking_number' => $replacement?->tracking_number,
                'replacement_url' => $replacement !== null ? $linkService->schedule($replacement) : null,
            ],
        ]);
    }
}
