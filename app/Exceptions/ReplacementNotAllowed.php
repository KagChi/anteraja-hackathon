<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ReplacementNotAllowed extends HttpException
{
    public function __construct(string $message = 'Pengajuan pengiriman ulang tidak memenuhi syarat.')
    {
        parent::__construct(422, $message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
