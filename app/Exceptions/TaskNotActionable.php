<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A task decision the domain refuses: accepting a parcel that is not assigned
 * to this courier, or rejecting one already accepted.
 *
 * Same reason as DeliveryNotActionable for the 4xx: the caller needs the
 * sentence, not a 500.
 */
final class TaskNotActionable extends HttpException
{
    public function __construct(string $message = 'Tugas ini tidak dapat diproses.')
    {
        parent::__construct(422, $message);
    }

    public static function alreadyAccepted(): self
    {
        return new self('Tugas sudah diterima, hubungi admin bila perlu dibatalkan.');
    }

    public static function notAssignable(string $tracking): self
    {
        return new self("Tugas untuk resi {$tracking} tidak dapat diproses.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
