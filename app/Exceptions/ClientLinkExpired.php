<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ClientLinkExpired extends HttpException
{
    public function __construct(string $message = 'Tautan pelacakan telah kedaluwarsa.')
    {
        parent::__construct(410, $message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'expired' => true,
            'customer_service' => config('coldchain.customer_service'),
        ], 410);
    }
}
