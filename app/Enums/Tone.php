<?php

declare(strict_types=1);

namespace App\Enums;

enum Tone: string
{
    case NEUTRAL = 'neutral';
    case MUTED = 'muted';
    case WARNING = 'warning';
    case INFO = 'info';
    case DANGER = 'danger';
    case DARK = 'dark';
    case SUCCESS = 'success';

    public function badgeClasses(): string
    {
        return match ($this) {
            self::NEUTRAL => 'bg-gray-100 text-gray-800',
            self::MUTED => 'bg-slate-100 text-slate-700',
            self::WARNING => 'bg-amber-100 text-amber-800',
            self::INFO => 'bg-blue-100 text-blue-800',
            self::DANGER => 'bg-rose-100 text-rose-800',
            self::DARK => 'bg-slate-900 text-white',
            self::SUCCESS => 'bg-emerald-100 text-emerald-800',
        };
    }
}
