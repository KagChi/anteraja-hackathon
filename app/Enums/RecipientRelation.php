<?php

declare(strict_types=1);

namespace App\Enums;

enum RecipientRelation: string
{
    case SELF = 'SELF';
    case FAMILY = 'FAMILY';
    case COLLEAGUE = 'COLLEAGUE';
    case SECURITY = 'SECURITY';

    public function label(): string
    {
        return match ($this) {
            self::SELF => 'Penerima Sendiri',
            self::FAMILY => 'Keluarga Serumah',
            self::COLLEAGUE => 'Rekan Kerja',
            self::SECURITY => 'Satpam / Sekuriti',
        };
    }

    public static function fromInput(string $value): ?self
    {
        $try = self::tryFrom(strtoupper(trim($value)));
        if ($try !== null) {
            return $try;
        }

        return match (trim($value)) {
            'Penerima Sendiri' => self::SELF,
            'Keluarga Serumah' => self::FAMILY,
            'Rekan Kerja' => self::COLLEAGUE,
            'Satpam / Sekuriti' => self::SECURITY,
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
