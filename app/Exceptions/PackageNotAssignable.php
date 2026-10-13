<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class PackageNotAssignable extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $msg = $message !== '' ? $message : __('coldchain.errors.package_not_assignable');

        parent::__construct($msg, $code, $previous);
    }
}
