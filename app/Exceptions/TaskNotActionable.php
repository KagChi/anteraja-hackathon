<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class TaskNotActionable extends RuntimeException
{
    public static function alreadyAccepted(): self
    {
        return new self('Tugas sudah diterima, hubungi admin bila perlu dibatalkan.');
    }

    public static function notAssignable(string $tracking): self
    {
        return new self("Tugas untuk resi {$tracking} tidak dapat diproses.");
    }
}
