<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappMessageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Titipan riwayat pesan dari workflow n8n: pesan masuk yang diterima WAHA dan
 * pesan keluar yang dikirim (atau gagal dikirim) ke pembeli, kurir, dan admin.
 */
final class WhatsappMessageLogController extends Controller
{
    public function __invoke(Request $request, WhatsappMessageLog $log): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'direction' => ['required', 'string', 'in:IN,OUT'],
            'body' => ['required', 'string', 'max:4000'],
            'status' => ['nullable', 'string', 'in:SENT,FAILED,RECEIVED'],
            'template' => ['nullable', 'string', 'max:64'],
            'tracking_number' => ['nullable', 'string', 'max:32'],
            'role' => ['nullable', 'string', 'in:BUYER,COURIER,ADMIN'],
            'external_id' => ['nullable', 'string', 'max:128'],
        ]);

        $externalId = isset($validated['external_id']) ? trim((string) $validated['external_id']) : null;

        $message = $validated['direction'] === 'IN'
            ? $log->recordInbound(
                phone: (string) $validated['phone'],
                body: (string) $validated['body'],
                externalId: $externalId,
                role: $validated['role'] ?? null,
                template: $validated['template'] ?? null,
                trackingNumber: $validated['tracking_number'] ?? null,
            )
            : $log->recordOutbound(
                phone: (string) $validated['phone'],
                body: (string) $validated['body'],
                template: $validated['template'] ?? null,
                status: $validated['status'] ?? null,
                trackingNumber: $validated['tracking_number'] ?? null,
                role: $validated['role'] ?? null,
                externalId: $externalId,
            );

        return response()->json([
            'recorded' => $message !== null,
            'id' => $message?->id,
        ]);
    }
}
