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

    /**
     * The first validation message Laravel sent, when the failure was a 422.
     *
     * A form screen shows this instead of the generic "The given data was
     * invalid." so the customer learns which field to fix.
     */
    public function firstError(): ?string {
        $errors = $this->raw['errors'] ?? null;

        if (! is_array($errors)) {
            return null;
        }

        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_string($messages[0])) {
                return $messages[0];
            }

            if (is_string($messages)) {
                return $messages;
            }
        }

        return null;
    }
}
