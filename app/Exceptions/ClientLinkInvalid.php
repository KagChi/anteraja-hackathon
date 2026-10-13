<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ClientLinkInvalid extends HttpException
{
    public function __construct(string $message = 'Tautan tidak valid atau paket tidak ditemukan.')
    {
        parent::__construct(404, $message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 404);
    }
}
