<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One normalized answer from the backend API.
 *
 * `raw` is the decoded body exactly as it arrived. `data()` unwraps the
 * `data` envelope for the endpoints that use one and otherwise hands back the
 * whole body, so both response shapes read the same way.
 */
final class ApiResult {
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly array $raw,
        public readonly ?string $message,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function success(array $raw, int $status = 200): self {
        return new self(true, $status, $raw, null);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failure(string $message, int $status = 0, array $raw = []): self {
        return new self(false, $status, $raw, $message);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function data(): array {
        $data = $this->raw['data'] ?? $this->raw;

        return is_array($data) ? $data : [];
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->raw[$key] ?? $default;
    }
}
