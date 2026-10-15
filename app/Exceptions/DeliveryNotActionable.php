<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A courier action the domain refuses: arriving before departure, reporting a
 * problem on a parcel that is not in the courier's hands, and so on.
 *
 * It answers with a 4xx and the message the app can show. As a plain
 * RuntimeException it surfaced as a 500, so a refusal read as a server fault.
 */
final class DeliveryNotActionable extends HttpException
{
    public function __construct(string $message = 'Aksi ini tidak dapat dijalankan untuk paket tersebut.')
    {
        parent::__construct(422, $message);
    }

    public static function issueAlreadyOpen(): self
    {
        return new self('Kendala sudah dilaporkan sebelumnya dan sedang menunggu keputusan admin.');
    }

    public static function invalidState(string $message): self
    {
        return new self($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
