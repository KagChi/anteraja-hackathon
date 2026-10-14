<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\NativeComponents\Client\History;
use App\NativeComponents\Client\Issue;
use App\NativeComponents\Client\Login;
use App\NativeComponents\Client\Orders;
use App\NativeComponents\Client\Profile;
use App\NativeComponents\Client\Schedule;
use App\NativeComponents\Client\Start;
use App\NativeComponents\Client\Tracking;
use App\Support\Appearance;
use App\Support\MobileToken;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Support\MobileApiFake;
use Tests\TestCase;

/**
 * The customer shell renders native UI, and reads every screen through the
 * backend API. Http is faked to stand in for the server; no screen loads a URL
 * in its interface and none contains a web view.
 */
final class ClientNativeScreensTest extends TestCase {
    private const TRANSPORTING = MobileApiFake::TRANSPORTING;

    private const WAITING = MobileApiFake::WAITING;

    private const ISSUE = MobileApiFake::ISSUE;

    protected function setUp(): void {
        parent::setUp();

        MobileToken::clear(MobileToken::CLIENT);
        MobileApiFake::stub();
    }

    public function test_the_orders_tab_lists_the_shipments_in_flight(): void {
        Native::test(Orders::class)
            ->assertSee('Pesanan')
            ->assertSee('3 pengiriman berjalan')
            ->assertSee(self::TRANSPORTING)
            ->assertSee('Es Krim Vanilla & Choco Box')
            // A parcel that already arrived belongs to the Riwayat tab.
            ->assertDontSee('AJ-FRZ-0012320');
    }

    public function test_the_history_tab_lists_the_parcels_that_arrived(): void {
        Native::test(History::class)
            ->assertSee('Riwayat')
            ->assertSee('2 pengiriman diterima')
            ->assertSee('96% Tepat Waktu')
            ->assertSee('AJ-FRZ-0012320')
            ->assertDontSee(self::TRANSPORTING);
    }

    public function test_the_profile_tab_shows_the_customer_and_the_support_channel(): void {
        Native::test(Profile::class)
            ->assertSee('Profil')
            ->assertSee('Rina Putri')
            ->assertSee('Jl. Melati No. 14')
            ->assertSee('Layanan Pelanggan Anteraja Frozen');
    }

    /** The profile carries the theme switcher, and picking a mode pins it. */
    public function test_the_profile_switches_the_app_theme(): void {
        $dark = config('native-ui.theme.dark');
        $light = config('native-ui.theme.light');

        Native::test(Profile::class)
            ->assertSee('Tema')
            ->assertSee('Sistem')
            ->assertSee('Terang')
            ->assertSee('Gelap')
            ->assertSet('appearance', 0)
            ->call('setAppearance', 2)
            ->assertSet('appearance', 2);

        $this->assertSame(Appearance::DARK, Appearance::get());

        // Pinned, not merely stored: the light block carries the dark palette
        // too, because the platform picks per element from the OS scheme.
        $this->assertSame($dark['background'], config('native-ui.theme.light.background'));
        $this->assertNotSame('#F9F9FF', config('native-ui.theme.light.background'));

        // And the next frame draws the pinned palette rather than the authored
        // light one — a resolved class string must not survive the switch.
        $pinned = json_encode(Native::test(Profile::class)->tree());
        $this->assertStringContainsString($dark['on-surface'], $pinned);
        $this->assertStringNotContainsString($light['on-surface'], $pinned);
    }

    /** "Sistem" hands the palette back to the device. */
    public function test_choosing_the_device_theme_restores_the_authored_palette(): void {
        $light = config('native-ui.theme.light');
        $dark = config('native-ui.theme.dark');

        Native::test(Profile::class)
            ->call('setAppearance', 1)
            ->assertSet('appearance', 1);

        $this->assertSame(Appearance::LIGHT, Appearance::get());
        $this->assertEquals($light, config('native-ui.theme.light'));
        $this->assertEquals($light, config('native-ui.theme.dark'));

        // A fresh mount reads the stored mode back, and the way out restores
        // the authored pair — the dark block derived from light again.
        Native::test(Profile::class)
            ->assertSet('appearance', 1)
            ->call('setAppearance', 0)
            ->assertSet('appearance', 0);

        $this->assertSame(Appearance::SYSTEM, Appearance::get());
        $this->assertEquals($light, config('native-ui.theme.light'));
        $this->assertEquals($dark, config('native-ui.theme.dark'));

        // The frame is back on the authored pair: the light palette is drawn
        // again, and the dark companion is back beside it.
        $restored = json_encode(Native::test(Profile::class)->tree());
        $this->assertStringContainsString($light['on-surface'], $restored);
        $this->assertStringContainsString('"dark_color"', $restored);
    }

    public function test_tracking_shows_the_cold_chain_reading_and_the_journey(): void {
        Native::test(Tracking::class, params: ['resi' => self::TRANSPORTING])
            ->assertSee('Pelacakan Kurir Beku')
            ->assertSee('-18°C')
            ->assertSee('Terjaga')
            ->assertSee('Riwayat Perjalanan')
            ->assertSee('Paket Dijemput');
    }

    /** The two extra actions belong to the two states that need them. */
    public function test_tracking_offers_the_action_its_state_calls_for(): void {
        Native::test(Tracking::class, params: ['resi' => self::WAITING])
            ->assertSee('Konfirmasi Jadwal')
            ->assertDontSee('Lihat Detail Kendala');

        Native::test(Tracking::class, params: ['resi' => self::ISSUE])
            ->assertSee('Lihat Detail Kendala')
            ->assertDontSee('Konfirmasi Jadwal');

        Native::test(Tracking::class, params: ['resi' => self::TRANSPORTING])
            ->assertDontSee('Konfirmasi Jadwal')
            ->assertDontSee('Lihat Detail Kendala');
    }

    public function test_tracking_calls_the_courier(): void {
        Native::test(Tracking::class, params: ['resi' => self::TRANSPORTING])
            ->call('callCourier')
            ->assertNativeCalled('Browser.Open', fn (array $params): bool => $params['url'] === 'tel:081234567890');
    }

    public function test_tracking_opens_the_schedule_and_the_issue_screens(): void {
        Native::test(Tracking::class, params: ['resi' => self::WAITING])
            ->call('openSchedule')
            ->assertNavigatedTo('/client/schedule/'.self::WAITING);

        Native::test(Tracking::class, params: ['resi' => self::ISSUE])
            ->call('openIssue')
            ->assertNavigatedTo('/client/issue/'.self::ISSUE);
    }

    public function test_the_help_action_asks_about_this_shipment(): void {
        Native::test(Tracking::class, params: ['resi' => self::TRANSPORTING])
            ->call('openSupport')
            ->assertNativeCalled('Browser.Open', fn (array $params): bool => str_contains($params['url'], 'wa.me')
                && str_contains($params['url'], self::TRANSPORTING));
    }

    public function test_the_schedule_screen_shows_the_window_and_the_frozen_cargo(): void {
        Native::test(Schedule::class, params: ['resi' => self::WAITING])
            ->assertSee('Konfirmasi Jadwal')
            ->assertSee('13.00 - 15.00 WIB')
            ->assertSee('-18°C Terjamin')
            ->assertSee('Es Krim Vanilla & Choco Box')
            ->assertSee('Prioritas Dingin');
    }

    public function test_confirming_the_window_is_final(): void {
        Native::test(Schedule::class, params: ['resi' => self::WAITING])
            ->assertSet('state', 'idle')
            ->assertSee('Siap Menerima')
            ->call('confirm')
            ->assertSet('state', 'confirmed')
            ->assertSee('Jadwal Terkonfirmasi')
            ->assertDontSee('Siap Menerima');

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), config('mobile.api_base_url'))
            && str_ends_with($request->url(), '/schedule/confirm'));
    }

    /** Asking for another window now posts the chosen slot, it does not leave the app. */
    public function test_requesting_another_window_posts_the_chosen_slot(): void {
        Native::test(Schedule::class, params: ['resi' => self::WAITING])
            ->assertDontSee('Pilih Jadwal Baru')
            ->call('requestOtherWindow')
            ->assertSet('rescheduleOpen', true)
            ->assertSee('Pilih Jadwal Baru')
            ->call('chooseRescheduleSlot', '13:00-15:00')
            ->call('submitReschedule')
            ->assertSet('state', 'rescheduled')
            ->assertSee('Permintaan Jadwal Terkirim');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/schedule/reschedule')
            && $request['slot'] === '13:00-15:00');
    }

    /** A rejected action stays on the screen with the reason beside it. */
    public function test_a_failed_action_shows_its_reason_without_losing_the_screen(): void {
        MobileApiFake::stub([
            '*api/client/orders/'.self::WAITING.'/schedule/confirm' => Http::response(['message' => 'Jadwal tidak dapat dikonfirmasi.'], 422),
        ]);

        Native::test(Schedule::class, params: ['resi' => self::WAITING])
            ->call('confirm')
            ->assertSet('state', 'idle')
            ->assertSee('Jadwal tidak dapat dikonfirmasi.')
            ->assertSee('Siap Menerima');
    }

    public function test_the_issue_screen_shows_the_decision_and_offers_a_resend(): void {
        Native::test(Issue::class, params: ['resi' => self::ISSUE])
            ->assertSee('Kendala Pengiriman')
            ->assertSee('Pengiriman Terkendala')
            ->assertSee('Keputusan Admin Operasional')
            ->assertSee('Riwayat Penanganan Hub')
            ->call('requestResend')
            ->assertNavigatedTo('/client/schedule/'.self::ISSUE);

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/issue/resend'));
    }

    public function test_the_login_screen_stores_the_token_and_enters_the_app(): void {
        Native::test(Login::class)
            ->assertSee('Anteraja Frozen')
            ->set('phone', '081298765432')
            ->set('password', 'password123')
            ->call('submit')
            ->assertNavigatedTo('/client/orders');

        $this->assertSame('client-test-token', MobileToken::get(MobileToken::CLIENT));
    }

    public function test_the_login_screen_refuses_empty_credentials(): void {
        Native::test(Login::class)
            ->call('submit')
            ->assertSee('Nomor HP dan password wajib diisi.');
    }

    public function test_the_start_gate_opens_login_without_a_token(): void {
        Native::test(Start::class)->assertNavigatedTo('/client/login');
    }

    public function test_the_start_gate_skips_login_when_signed_in(): void {
        MobileToken::set(MobileToken::CLIENT, 'client-test-token');

        Native::test(Start::class)->assertNavigatedTo('/client/orders');
    }

    /** The gate and login are their own routes, outside the tab group. */
    public function test_the_auth_routes_resolve(): void {
        Native::visit('/client/start')->assertNavigatedTo('/client/login');

        Native::visit('/client/login')
            ->assertSee('Anteraja Frozen')
            ->assertMissingElement('tab-bar');
    }

    public function test_a_failed_call_renders_the_retry_state(): void {
        MobileApiFake::stub(['*' => Http::response(['message' => 'Server sedang gangguan.'], 500)]);

        Native::test(Orders::class)
            ->assertSee('Gagal memuat data')
            ->assertSee('Server sedang gangguan.')
            ->assertSee('Coba Lagi');
    }

    public function test_retry_reloads_after_a_failure(): void {
        MobileApiFake::stub(['*' => Http::response(['message' => 'Server sedang gangguan.'], 500)]);

        $screen = Native::test(Orders::class)->assertSee('Gagal memuat data');

        MobileApiFake::stub();

        $screen->call('retry')
            ->assertDontSee('Gagal memuat data')
            ->assertSee('3 pengiriman berjalan');
    }

    /** An unknown resi cannot render an empty screen. */
    public function test_an_unknown_resi_shows_the_not_found_card(): void {
        foreach ([Tracking::class, Schedule::class, Issue::class] as $screen) {
            Native::test($screen, params: ['resi' => 'AJ-FRZ-0000000'])
                ->assertSee('Pesanan tidak ditemukan')
                ->assertSee('AJ-FRZ-0000000');
        }
    }

    /** The web view is gone; nothing may reintroduce it. */
    public function test_no_screen_renders_a_web_view(): void {
        $screens = [
            Orders::class,
            History::class,
            Profile::class,
            Tracking::class,
            Schedule::class,
            Issue::class,
            Login::class,
            Start::class,
        ];

        foreach ($screens as $screen) {
            Native::test($screen)->assertMissingElement('webview');
        }
    }

    /** The tab section is reachable by route, and every tab resolves. */
    public function test_the_customer_tabs_are_routable(): void {
        Native::visit('/client/orders')
            ->assertHasTabBar()
            ->assertHasTab('Pesanan')
            ->assertHasTab('Riwayat')
            ->assertHasTab('Profil');
    }

    /** The pushed screens are reachable by their own routes, params and all. */
    public function test_the_pushed_customer_routes_resolve(): void {
        Native::visit('/client/tracking/'.self::TRANSPORTING)
            ->assertSee('Pelacakan Kurir Beku')
            ->assertSee(self::TRANSPORTING);

        Native::visit('/client/schedule/'.self::WAITING)
            ->assertSee('Prioritas Dingin');

        Native::visit('/client/issue/'.self::ISSUE)
            ->assertSee('Kendala Pengiriman');
    }
}
