<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\Concerns\LoadsFromApi;
use App\Support\MobileApi;
use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Courier identity, today's numbers and the day's assignment —
 * GET /api/courier/profile.
 */
final class Profile extends NativeComponent {
    use LoadsFromApi;

    /** @var array<string, mixed> */
    public array $profile = [];

    public function mount(): void {
        $this->load();
    }

    protected function load(): void {
        $result = MobileApi::courier()->get('/courier/profile');

        if (! $result->ok) {
            $this->error = $result->message;
            $this->profile = self::emptyProfile();

            return;
        }

        $profile = $result->get('profile');
        $this->profile = is_array($profile) ? $profile : self::emptyProfile();
    }

    public function logout(): void {
        MobileApi::courier()->post('/courier/auth/logout');
        MobileToken::clear(MobileToken::COURIER);

        $this->navigate('/courier/login');
    }

    public function render(): Element {
        return $this->view('courier.profile', [
            'profile' => $this->profile,
            'error' => $this->error,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyProfile(): array {
        return [
            'name' => '',
            'status' => '',
            'id' => '',
            'phone' => '-',
            'hub' => '',
            'vehicle' => '',
            'stats' => [
                ['label' => 'Selesai hari ini', 'value' => '0'],
                ['label' => 'Tepat waktu', 'value' => '97%'],
                ['label' => 'Rating', 'value' => '4.8'],
            ],
        ];
    }
}
