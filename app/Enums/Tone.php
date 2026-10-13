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
            self::NEUTRAL => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::MUTED => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
            self::WARNING => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            self::INFO => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            self::DANGER => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
            self::DARK => 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900',
            self::SUCCESS => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        };
    }
}
