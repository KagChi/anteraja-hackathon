<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\NativeComponents\Client\History;
use App\NativeComponents\Client\Issue;
use App\NativeComponents\Client\Orders;
use App\NativeComponents\Client\Profile;
use App\NativeComponents\Client\Schedule;
use App\NativeComponents\Client\Tracking;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

/**
 * The customer shell renders native UI: NativeComponent screens built from
 * EDGE elements, compiled to SwiftUI and Jetpack Compose. No screen loads a
 * URL in the interface and none contains a web view — these tests hold that
 * line, and cover the state each screen carries.
 */
final class ClientNativeScreensTest extends TestCase {
    private const TRANSPORTING = 'AJ-FRZ-0012345';

    private const WAITING = 'AJ-FRZ-0012347';

    private const ISSUE = 'AJ-FRZ-0012349';

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
    }

    public function test_asking_for_another_window_leaves_through_whatsapp(): void {
        Native::test(Schedule::class, params: ['resi' => self::WAITING])
            ->call('requestOtherWindow')
            ->assertNativeCalled('Browser.Open', fn (array $params): bool => str_contains($params['url'], 'wa.me')
                && str_contains($params['url'], self::WAITING));
    }

    public function test_the_issue_screen_shows_the_decision_and_offers_a_resend(): void {
        Native::test(Issue::class, params: ['resi' => self::ISSUE])
            ->assertSee('Kendala Pengiriman')
            ->assertSee('Pengiriman Terkendala')
            ->assertSee('Keputusan Admin Operasional')
            ->assertSee('Riwayat Penanganan Hub')
            ->call('requestResend')
            ->assertNavigatedTo('/client/schedule/'.self::ISSUE);
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
