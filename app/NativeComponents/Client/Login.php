<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\MobileApi;
use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * The customer sign-in: phone + password against /api/client/auth/login, and
 * the Sanctum token it hands back is stored for the rest of the app.
 */
final class Login extends NativeComponent {
    public string $phone = '';

    public string $password = '';

    public ?string $error = null;

    public bool $busy = false;

    public function mount(): void {
        if (MobileToken::get(MobileToken::CLIENT) !== null) {
            $this->navigate('/client/orders');
        }
    }

    public function submit(): void {
        if ($this->busy) {
            return;
        }

        $this->error = null;

        if (trim($this->phone) === '' || $this->password === '') {
            $this->error = 'Nomor HP dan password wajib diisi.';

            return;
        }

        $this->busy = true;
        $result = MobileApi::client()->post('/client/auth/login', [
            'phone' => trim($this->phone),
            'password' => $this->password,
        ]);
        $this->busy = false;

        if (! $result->ok) {
            $this->error = $result->message;

            return;
        }

        $token = $result->data()['token'] ?? null;
        if (! is_string($token) || $token === '') {
            $this->error = 'Login gagal: token tidak diterima.';

            return;
        }

        MobileToken::set(MobileToken::CLIENT, $token);
        $this->navigate('/client/orders');
    }

    public function render(): Element {
        return $this->view('client.login', [
            'error' => $this->error,
            'busy' => $this->busy,
        ]);
    }
}
