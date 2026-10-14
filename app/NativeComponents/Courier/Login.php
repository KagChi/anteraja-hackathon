<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\MobileApi;
use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * The courier sign-in: staff email + password against
 * /api/courier/auth/login, and the Sanctum token it hands back is stored for
 * the rest of the app.
 */
final class Login extends NativeComponent {
    public string $email = '';

    public string $password = '';

    public ?string $error = null;

    public bool $busy = false;

    public function mount(): void {
        if (MobileToken::get(MobileToken::COURIER) !== null) {
            $this->navigate('/courier/tasks');
        }
    }

    public function submit(): void {
        if ($this->busy) {
            return;
        }

        $this->error = null;

        if (trim($this->email) === '' || $this->password === '') {
            $this->error = 'Email dan password wajib diisi.';

            return;
        }

        $this->busy = true;
        $result = MobileApi::courier()->post('/courier/auth/login', [
            'email' => trim($this->email),
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

        MobileToken::set(MobileToken::COURIER, $token);
        $this->navigate('/courier/tasks');
    }

    public function render(): Element {
        return $this->view('courier.login', [
            'error' => $this->error,
            'busy' => $this->busy,
        ]);
    }
}
