<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PackageNotAssignable extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        // The admin console always reads the message, so the translation is
        // pinned to the language the rest of the console speaks instead of
        // following APP_LOCALE, which the mobile builds keep at "en".
        $msg = $message !== '' ? $message : __('coldchain.errors.package_not_assignable', [], 'id');

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
