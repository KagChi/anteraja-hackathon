<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ScheduleNotActionable extends HttpException
{
    public function __construct(string $message = 'Jadwal pengantaran tidak dapat diubah atau sudah dikonfirmasi.')
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
