<?php

declare(strict_types=1);

namespace App\Support;

final class ChecklistNormalizer
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, array{answer: bool, detail: string|null}>
     */
    public static function normalize(array $raw): array
    {
        $allowedKeys = array_keys(config('coldchain.checklist_items', []));
        $allowedKeys[] = 'buyer_rejected';

        $normalized = [];

        foreach ($allowedKeys as $key) {
            $entry = $raw[$key] ?? null;

            if (is_array($entry)) {
                $answer = filter_var($entry['answer'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $detail = isset($entry['detail']) && is_string($entry['detail']) ? trim(substr($entry['detail'], 0, 100)) : null;
            } elseif (is_bool($entry)) {
                $answer = $entry;
                $detail = null;
            } else {
                $answer = false;
                $detail = null;
            }

            $normalized[$key] = [
                'answer' => $answer,
                'detail' => $detail !== '' ? $detail : null,
            ];
        }

        return $normalized;
    }
}
