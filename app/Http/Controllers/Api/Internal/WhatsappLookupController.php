<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Client\ClientLinkService;
use App\Services\Client\OrderPresenter;
use App\Support\PhoneFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resolves a tracking number a customer typed on WhatsApp into that customer's
 * package, so the n8n inbound workflow can attach the conversation to it.
 *
 * Matching needs BOTH the resi and a buyer whose phone equals the number the
 * message came from, so a customer can only ever reach their own parcels.
 */
final class WhatsappLookupController extends Controller
{
    public function __invoke(
        Request $request,
        OrderPresenter $presenter,
        ClientLinkService $linkService,
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'resi' => ['required', 'string', 'max:32'],
        ]);

        $phone = PhoneFormatter::toE164Id($validated['phone']);
        $resi = strtoupper(trim($validated['resi']));

        /** @var Package|null $package */
        $package = Package::query()
            ->where('tracking_number', $resi)
            ->whereHas('buyer', fn ($query) => $query->where('phone', $phone))
            ->with(['buyer', 'courier', 'items.product'])
            ->first();

        if ($package === null) {
            // Not an HTTP error: the workflow treats "no match" as a normal
            // answer and replies with the help text.
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'tracking_number' => $package->tracking_number,
            'client_token' => $package->client_token,
            'client_url' => $linkService->entry($package),
            'template' => $this->templateFor($presenter, $package),
            'status_label' => (string) __("client.status.{$package->status->value}", [], 'id'),
        ]);
    }

    /**
     * What this package is waiting for, expressed as the notification template
     * that would have been sent — the workflow keys its replies off this, so
     * the domain decides what is pending rather than the workflow guessing.
     */
    private function templateFor(OrderPresenter $presenter, Package $package): string
    {
        return match ($presenter->resolveActionRequired($package)) {
            'confirm_schedule' => 'schedule_request',
            'view_issue' => 'issue_options',
            'request_replacement' => 'issue_decision',
            'track' => 'in_delivery',
            default => 'none',
        };
    }
}
