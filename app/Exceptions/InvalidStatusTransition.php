<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidStatusTransition extends RuntimeException
{
    public function __construct(
        public readonly string $fromStatus,
        public readonly string $toStatus,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $msg = $message !== '' ? $message : __('coldchain.errors.invalid_status_transition', [
            'from' => $fromStatus,
            'to' => $toStatus,
        ], 'id');

        parent::__construct($msg, $code, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
