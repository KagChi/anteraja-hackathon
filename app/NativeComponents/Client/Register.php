<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\MobileApi;
use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Customer sign-up.
 *
 * There is no OTP step: the phone number is the account identifier and the
 * password is chosen here, matching what the register endpoint accepts. Once
 * the token is stored the flow continues straight to checkout, because the
 * customer almost always arrived here by trying to order.
 */
final class Register extends NativeComponent {
    public string $name = '';

    public string $phone = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $address = '';

    public string $area = '';

    public string $addressType = 'Rumah / Residensial';

    public ?string $error = null;

    public bool $busy = false;

    public function mount(): void {
        if (MobileToken::get(MobileToken::CLIENT) !== null) {
            $this->navigate('/client/checkout');
        }
    }

    public function submit(): void {
        if ($this->busy) {
            return;
        }

        $this->error = null;

        if (trim($this->name) === '' || trim($this->phone) === '' || trim($this->address) === '') {
            $this->error = 'Nama, nomor HP, dan alamat wajib diisi.';

            return;
        }

        if (mb_strlen($this->password) < 8) {
            $this->error = 'Password minimal 8 karakter.';

            return;
        }

        if ($this->password !== $this->passwordConfirmation) {
            $this->error = 'Konfirmasi password tidak sama.';

            return;
        }

        $this->busy = true;
        $result = MobileApi::client()->post('/client/auth/register', [
            'name' => trim($this->name),
            'phone' => trim($this->phone),
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
            'address' => trim($this->address),
            'area' => trim($this->area) !== '' ? trim($this->area) : null,
            'address_type' => str_contains($this->addressType, 'Kantor') ? 'COMMERCIAL' : 'RESIDENTIAL',
        ]);
        $this->busy = false;

        if (! $result->ok) {
            $this->error = $result->firstError() ?? $result->message;

            return;
        }

        $token = $result->data()['token'] ?? null;
        if (! is_string($token) || $token === '') {
            $this->error = 'Pendaftaran gagal: token tidak diterima.';

            return;
        }

        MobileToken::set(MobileToken::CLIENT, $token);
        $this->navigate('/client/checkout');
    }

    public function goToLogin(): void {
        $this->navigate('/client/login');
    }

    /**
     * @return list<string>
     */
    public function addressTypeOptions(): array {
        return ['Rumah / Residensial', 'Kantor / Komersial'];
    }

    public function render(): Element {
        return $this->view('client.register', [
            'error' => $this->error,
            'busy' => $this->busy,
            'typeOptions' => $this->addressTypeOptions(),
        ]);
    }
}
