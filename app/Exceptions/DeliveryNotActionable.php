<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DeliveryNotActionable extends RuntimeException
{
    public static function issueAlreadyOpen(): self
    {
        return new self('Kendala sudah dilaporkan sebelumnya dan sedang menunggu keputusan admin.');
    }

    public static function invalidState(string $message): self
    {
        return new self($message);
    }
}
