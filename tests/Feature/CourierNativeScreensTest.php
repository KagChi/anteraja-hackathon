<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\NativeComponents\Courier\Delivery;
use App\NativeComponents\Courier\History;
use App\NativeComponents\Courier\Issue;
use App\NativeComponents\Courier\Login;
use App\NativeComponents\Courier\Profile;
use App\NativeComponents\Courier\Proof;
use App\NativeComponents\Courier\Start;
use App\NativeComponents\Courier\Tasks;
use App\Support\Appearance;
use App\Support\MobileToken;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Support\MobileApiFake;
use Tests\TestCase;

/**
 * The courier shell renders native UI, and reads every screen through the
 * backend API. Http is faked to stand in for the server; no screen loads a URL
 * in its interface and none contains a web view.
 */
final class CourierNativeScreensTest extends TestCase {
    private const TASK = MobileApiFake::COURIER_TASK;

    protected function setUp(): void {
        parent::setUp();

        MobileToken::clear(MobileToken::COURIER);
        MobileApiFake::stub();
    }

    public function test_the_task_board_shows_the_assigned_delivery(): void {
        Native::test(Tasks::class)
            ->assertSee('Daftar Tugas')
            ->assertSee(self::TASK)
            ->assertSee('Rina Putri')
            ->assertSee('Instruksi Khusus');
    }

    /** Every call goes to the configured host, carrying the stored token. */
    public function test_requests_carry_the_stored_token_and_the_configured_host(): void {
        MobileToken::set(MobileToken::COURIER, 'courier-test-token');

        Native::test(Tasks::class);

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), config('mobile.api_base_url'))
            && $request->hasHeader('Authorization', 'Bearer courier-test-token'));
    }

    public function test_accepting_the_task_locks_the_decision_and_confirms_it(): void {
        Native::test(Tasks::class)
            ->tap('Terima Tugas')
            ->assertSet('state', 'accepted')
            ->assertSee('Tugas berhasil diterima!');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/accept'));
    }

    public function test_rejecting_the_task_names_it_in_the_notice(): void {
        Native::test(Tasks::class)
            ->tap('Tolak')
            ->assertSet('state', 'rejected')
            ->assertSee(self::TASK.' telah ditolak.');
    }

    public function test_the_notice_can_be_dismissed(): void {
        Native::test(Tasks::class)
            ->tap('Terima Tugas')
            ->call('dismissToast')
            ->assertSet('toastDismissed', true)
            ->assertDontSee('Tugas berhasil diterima!');
    }

    /** A rejected action stays on the screen with the reason beside it. */
    public function test_a_failed_action_shows_its_reason_without_losing_the_screen(): void {
        MobileApiFake::stub([
            '*api/courier/tasks/'.self::TASK.'/accept' => Http::response(['message' => 'Tugas sudah tidak tersedia.'], 422),
        ]);

        Native::test(Tasks::class)
            ->call('accept')
            ->assertSet('state', 'pending')
            ->assertSee('Tugas sudah tidak tersedia.')
            ->assertSee('Terima Tugas');
    }

    public function test_the_history_tab_lists_completed_deliveries(): void {
        Native::test(History::class)
            ->assertSee('Riwayat Pengiriman')
            ->assertSee(MobileApiFake::COURIER_HISTORY)
            ->assertSee('Andi Saputra')
            ->assertSee('Dikembalikan');
    }

    public function test_the_profile_tab_shows_identity_and_assignment(): void {
        Native::test(Profile::class)
            ->assertSee('Profil Kurir')
            ->assertSee('Budi Santoso')
            ->assertSee('Motor Box #04');
    }

    /** The profile carries the theme switcher, and picking a mode pins it. */
    public function test_the_profile_switches_the_app_theme(): void {
        $light = config('native-ui.theme.light');

        Native::test(Profile::class)
            ->assertSee('Tema')
            ->assertSee('Sistem')
            ->assertSee('Terang')
            ->assertSee('Gelap')
            ->assertSet('appearance', 0)
            ->call('setAppearance', 1)
            ->assertSet('appearance', 1);

        $this->assertSame(Appearance::LIGHT, Appearance::get());

        // Pinned, not merely stored: the dark block carries the light palette
        // too, because the platform picks per element from the OS scheme.
        $this->assertSame($light['surface'], config('native-ui.theme.dark.surface'));
    }

    public function test_the_delivery_screen_offers_the_route_and_the_handover(): void {
        Native::test(Delivery::class, params: ['resi' => self::TASK])
            ->assertSee('Detail Paket')
            ->assertSee('Rina Putri')
            ->assertSee('Buka Navigasi Rute')
            ->assertSee('Serahkan Paket');
    }

    public function test_the_delivery_screen_opens_the_issue_sheet(): void {
        Native::test(Delivery::class, params: ['resi' => self::TASK])
            ->assertDontSee('Pilih Kendala')
            ->call('openSheet')
            ->assertSet('sheetOpen', true)
            ->assertSee('Pilih Kendala')
            ->assertSee('Paket Rusak / Mencair');
    }

    public function test_arriving_at_the_address_is_recorded(): void {
        Native::test(Delivery::class, params: ['resi' => self::TASK])
            ->assertSet('arrived', false)
            ->call('markArrived')
            ->assertSet('arrived', true);

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/arrive'));
    }

    public function test_proof_will_not_submit_without_a_recipient_name(): void {
        Native::test(Proof::class, params: ['resi' => self::TASK])
            ->assertSee('Konfirmasi Pengiriman')
            ->set('recipientName', '   ')
            ->call('submit')
            ->assertSet('state', 'idle');
    }

    public function test_proof_completes_the_delivery(): void {
        Native::test(Proof::class, params: ['resi' => self::TASK])
            ->call('chooseRelation', 'Keluarga Serumah')
            ->assertSet('relation', 'Keluarga Serumah')
            ->call('submit')
            ->assertSet('state', 'done')
            ->assertSee('Pengiriman Berhasil Selesai');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/proof'));
    }

    public function test_issue_reports_what_went_wrong(): void {
        Native::test(Issue::class, params: ['resi' => self::TASK])
            ->assertSee('Lapor Masalah')
            ->assertSee('Kendala Lapangan')
            ->call('submit')
            ->assertSet('state', 'done')
            ->assertSee('Laporan Terkirim');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/issue'));
    }

    public function test_the_login_screen_stores_the_token_and_enters_the_app(): void {
        Native::test(Login::class)
            ->assertSee('Anteraja Kurir')
            ->set('email', 'budi@anteraja.id')
            ->set('password', 'password')
            ->call('submit')
            ->assertNavigatedTo('/courier/tasks');

        $this->assertSame('courier-test-token', MobileToken::get(MobileToken::COURIER));
    }

    public function test_the_start_gate_opens_login_without_a_token(): void {
        Native::test(Start::class)->assertNavigatedTo('/courier/login');
    }

    public function test_the_start_gate_skips_login_when_signed_in(): void {
        MobileToken::set(MobileToken::COURIER, 'courier-test-token');

        Native::test(Start::class)->assertNavigatedTo('/courier/tasks');
    }

    /** The gate and login are their own routes, outside the tab group. */
    public function test_the_auth_routes_resolve(): void {
        Native::visit('/courier/start')->assertNavigatedTo('/courier/login');

        Native::visit('/courier/login')
            ->assertSee('Anteraja Kurir')
            ->assertMissingElement('tab-bar');
    }

    public function test_a_failed_call_renders_the_retry_state(): void {
        MobileApiFake::stub(['*' => Http::response(['message' => 'Server sedang gangguan.'], 500)]);

        Native::test(Tasks::class)
            ->assertSee('Gagal memuat data')
            ->assertSee('Coba Lagi');
    }

    /** The web view is gone; nothing may reintroduce it. */
    public function test_no_screen_renders_a_web_view(): void {
        $screens = [
            Tasks::class,
            History::class,
            Profile::class,
            Delivery::class,
            Proof::class,
            Issue::class,
            Login::class,
            Start::class,
        ];

        foreach ($screens as $screen) {
            Native::test($screen)->assertMissingElement('webview');
        }
    }

    /** The tab section is reachable by route, and every tab resolves. */
    public function test_the_courier_tabs_are_routable(): void {
        Native::visit('/courier/tasks')
            ->assertHasTabBar()
            ->assertHasTab('Tugas')
            ->assertHasTab('Riwayat')
            ->assertHasTab('Profil');
    }
}
