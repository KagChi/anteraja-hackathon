<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\Concerns\LoadsFromApi;
use App\Support\Appearance;
use App\Support\MobileApi;
use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Courier identity, today's numbers, the day's assignment, and the app-wide
 * theme switcher — GET /api/courier/profile.
 */
final class Profile extends NativeComponent {
    use LoadsFromApi;

    /** Selected index into Appearance::modes() — the switcher's bound value. */
    public int $appearance = 0;

    /** @var array<string, mixed> */
    public array $profile = [];

    public function mount(): void {
        $this->appearance = Appearance::index();
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

    /** Pin the app palette; the next frame draws in it. */
    public function setAppearance(int $index): void {
        Appearance::select($index);
        $this->appearance = Appearance::index();
    }

    public function logout(): void {
        MobileApi::courier()->post('/courier/auth/logout');
        MobileToken::clear(MobileToken::COURIER);

        $this->navigate('/courier/login');
    }

    public function render(): Element {
        return $this->view('courier.profile', [
            'profile' => $this->profile,
            'appearance' => $this->appearance,
            'appearanceOptions' => Appearance::labels(),
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
