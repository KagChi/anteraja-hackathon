<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class IssueAlreadyDecided extends RuntimeException
{
    public function __construct(
        public readonly ?string $decidedBy = null,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $msg = $message !== '' ? $message : __('coldchain.errors.issue_already_decided', [
            'decided_by' => $decidedBy ?? 'admin lain',
        ]);

        parent::__construct($msg, $code, $previous);
    }
}
