<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessage;
use App\Support\DisplayTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Riwayat pesan WhatsApp untuk dashboard admin: arah, pengirim atau penerima,
 * isi, waktu, dan status terkirimnya.
 */
final class MessageHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resi' => ['nullable', 'string', 'max:32'],
            'direction' => ['nullable', 'string', 'in:IN,OUT'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = WhatsappMessage::query()->orderByDesc('id');

        $resi = isset($validated['resi']) ? strtoupper(trim((string) $validated['resi'])) : '';
        if ($resi !== '') {
            $query->where('tracking_number', $resi);
        }

        if (isset($validated['direction']) && $validated['direction'] !== '') {
            $query->where('direction', (string) $validated['direction']);
        }

        $paginator = $query->paginate(
            (int) ($validated['per_page'] ?? 20),
            ['*'],
            'page',
            (int) ($validated['page'] ?? 1)
        );

        return response()->json([
            'messages' => collect($paginator->items())
                ->map(fn (WhatsappMessage $message) => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'role' => $message->role,
                    'phone' => $message->phone,
                    'template' => $message->template,
                    'body' => $message->body,
                    'reply_body' => $message->reply_body,
                    'status' => $message->status,
                    'tracking_number' => $message->tracking_number,
                    'at' => DisplayTime::toDisplay($message->created_at)?->format('d M Y, H:i').' WIB',
                    'at_iso' => $message->created_at?->toIso8601String(),
                ])
                ->all(),
            'paginator' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
