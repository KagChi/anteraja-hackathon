<?php

declare(strict_types=1);

namespace App\DTOs\Courier;

use App\Enums\RecipientRelation;

final readonly class ConfirmDeliveryData {
    public function __construct(
        public string $receivedBy,
        public RecipientRelation $recipientRelation,
        public string $photoPath,
        public string $pin,
        public ?string $receiptNote = null,
    ) {
    }
}
