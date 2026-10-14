<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The one door the native screens use to reach the backend.
 *
 * It carries the realm's bearer token, prefixes the configured API host, and
 * folds every transport outcome — connect failure, timeout, non-2xx — into an
 * ApiResult, so a screen branches on a single shape instead of on Guzzle.
 */
final class MobileApi {
    public function __construct(private readonly string $realm) {}

    public static function client(): self {
        return new self(MobileToken::CLIENT);
    }

    public static function courier(): self {
        return new self(MobileToken::COURIER);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): ApiResult {
        return $this->send(fn (PendingRequest $request) => $request->get($path, $query));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $path, array $data = []): ApiResult {
        return $this->send(fn (PendingRequest $request) => $request->post($path, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function postFile(string $path, array $data, ?string $filePath, string $field = 'photo'): ApiResult {
        return $this->send(function (PendingRequest $request) use ($path, $data, $filePath, $field) {
            if ($filePath !== null && is_file($filePath)) {
                $request = $request->attach($field, file_get_contents($filePath) ?: '', basename($filePath));
            }

            return $request->post($path, $data);
        });
    }

    private function send(callable $callback): ApiResult {
        try {
            $request = Http::baseUrl((string) config('mobile.api_base_url'))
                ->acceptJson()
                ->timeout((int) config('mobile.timeout', 20));

            $token = MobileToken::get($this->realm);
            if ($token !== null) {
                $request = $request->withToken($token);
            }

            $response = $callback($request);
        } catch (Throwable) {
            return ApiResult::failure('Tidak dapat terhubung ke server. Periksa koneksi Anda lalu coba lagi.');
        }

        $raw = $response->json();
        $raw = is_array($raw) ? $raw : [];

        if ($response->failed()) {
            $message = is_string($raw['message'] ?? null)
                ? $raw['message']
                : 'Permintaan gagal ('.$response->status().').';

            return ApiResult::failure($message, $response->status(), $raw);
        }

        return ApiResult::success($raw, $response->status());
    }
}
