<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappMessageLog;
use App\Services\Whatsapp\WhatsappStaffAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aksi admin dari balasan WhatsApp.
 *
 * Dipanggil workflow n8n setelah mengenali niat balasan. Nomor pengirim
 * menentukan siapa yang bertindak, dan message_id dari WhatsApp membuat pesan
 * yang sama tidak pernah dijalankan dua kali: balasan dobel menerima jawaban
 * yang sama persis tanpa mengubah data lagi. Kurir tidak memakai WhatsApp;
 * nomor kurir yang membalas ditolak.
 */
final class WhatsappActionController extends Controller {
    public function __invoke(
        Request $request,
        WhatsappStaffAction $action,
        WhatsappMessageLog $log,
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'action' => ['required', 'string', 'max:32'],
            'resi' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:32'],
            'text' => ['nullable', 'string', 'max:1000'],
            'message_id' => ['nullable', 'string', 'max:128'],
        ]);

        $messageId = isset($validated['message_id']) ? trim((string) $validated['message_id']) : '';

        if ($messageId !== '') {
            $previous = $log->findInbound($messageId);

            if ($previous !== null && $previous->reply_body !== null) {
                return response()->json([
                    'success' => true,
                    'role' => $previous->role,
                    'template' => $previous->template,
                    'tracking_number' => $previous->tracking_number,
                    'message' => $previous->reply_body,
                    'duplicate' => true,
                ]);
            }
        }

        $result = $action->handle(
            phone: (string) $validated['phone'],
            action: strtolower(trim((string) $validated['action'])),
            resi: isset($validated['resi']) ? (string) $validated['resi'] : null,
            note: isset($validated['note']) ? (string) $validated['note'] : null,
            category: isset($validated['category']) ? (string) $validated['category'] : null,
        );

        $log->recordInbound(
            phone: (string) $validated['phone'],
            body: isset($validated['text']) && trim((string) $validated['text']) !== ''
                ? (string) $validated['text']
                : (string) $validated['action'],
            externalId: $messageId === '' ? null : $messageId,
            reply: $result['message'],
            template: $result['template'],
            trackingNumber: $result['tracking_number'],
            role: $result['role'],
        );

        return response()->json($result);
    }
}
