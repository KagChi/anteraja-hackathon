<?php

declare(strict_types=1);

namespace App\Enums;

enum AddressType: string
{
    case RESIDENTIAL = 'RESIDENTIAL';
    case COMMERCIAL = 'COMMERCIAL';

    public function label(): string
    {
        return match ($this) {
            self::RESIDENTIAL => 'Rumah / Residensial',
            self::COMMERCIAL => 'Kantor / Komersial',
        };
    }

    /**
     * Accepts the API value as well as the Indonesian label the customer app
     * sends from its picker.
     */
    public static function fromInput(string $value): ?self
    {
        $try = self::tryFrom(strtoupper(trim($value)));
        if ($try !== null) {
            return $try;
        }

        return match (mb_strtolower(trim($value))) {
            'rumah', 'residensial' => self::RESIDENTIAL,
            'kantor', 'komersial' => self::COMMERCIAL,
            default => null,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
