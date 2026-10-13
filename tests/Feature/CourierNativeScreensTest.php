<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\NativeComponents\Courier\Delivery;
use App\NativeComponents\Courier\History;
use App\NativeComponents\Courier\Issue;
use App\NativeComponents\Courier\Profile;
use App\NativeComponents\Courier\Proof;
use App\NativeComponents\Courier\Tasks;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

/**
 * The Android shell renders native UI: NativeComponent screens built from EDGE
 * elements, compiled to SwiftUI and Jetpack Compose. No screen loads a URL and
 * none contains a web view — these tests hold that line, and cover the state
 * each screen carries.
 */
final class CourierNativeScreensTest extends TestCase {
    public function test_the_task_board_shows_the_assigned_delivery(): void {
        Native::test(Tasks::class)
            ->assertSee('Daftar Tugas')
            ->assertSee('AJ-FRZ-0012345')
            ->assertSee('Rina Putri')
            ->assertSee('Instruksi Khusus');
    }

    public function test_accepting_the_task_locks_the_decision_and_confirms_it(): void {
        Native::test(Tasks::class)
            ->tap('Terima Tugas')
            ->assertSet('state', 'accepted')
            ->assertSee('Tugas berhasil diterima!');
    }

    public function test_rejecting_the_task_names_it_in_the_notice(): void {
        Native::test(Tasks::class)
            ->tap('Tolak')
            ->assertSet('state', 'rejected')
            ->assertSee('AJ-FRZ-0012345 telah ditolak.');
    }

    public function test_the_notice_can_be_dismissed(): void {
        Native::test(Tasks::class)
            ->tap('Terima Tugas')
            ->call('dismissToast')
            ->assertSet('toastDismissed', true)
            ->assertDontSee('Tugas berhasil diterima!');
    }

    public function test_the_history_tab_lists_completed_deliveries(): void {
        Native::test(History::class)
            ->assertSee('Riwayat Pengiriman')
            ->assertSee('AJ-FRZ-0012344')
            ->assertSee('Andi Saputra')
            ->assertSee('Dikembalikan');
    }

    public function test_the_profile_tab_shows_identity_and_assignment(): void {
        Native::test(Profile::class)
            ->assertSee('Profil Kurir')
            ->assertSee('Budi Santoso')
            ->assertSee('Motor Box #04');
    }

    public function test_the_delivery_screen_offers_the_route_and_the_handover(): void {
        Native::test(Delivery::class, params: ['resi' => 'AJ-FRZ-0012345'])
            ->assertSee('Detail Paket')
            ->assertSee('Rina Putri')
            ->assertSee('Buka Navigasi Rute')
            ->assertSee('Serahkan Paket');
    }

    public function test_the_delivery_screen_opens_the_issue_sheet(): void {
        Native::test(Delivery::class, params: ['resi' => 'AJ-FRZ-0012345'])
            ->assertDontSee('Pilih Kendala')
            ->call('openSheet')
            ->assertSet('sheetOpen', true)
            ->assertSee('Pilih Kendala')
            ->assertSee('Paket Rusak / Mencair');
    }

    public function test_arriving_at_the_address_is_recorded(): void {
        Native::test(Delivery::class, params: ['resi' => 'AJ-FRZ-0012345'])
            ->assertSet('arrived', false)
            ->call('markArrived')
            ->assertSet('arrived', true);
    }

    public function test_proof_will_not_submit_without_a_recipient_name(): void {
        Native::test(Proof::class, params: ['resi' => 'AJ-FRZ-0012345'])
            ->assertSee('Konfirmasi Pengiriman')
            ->set('recipientName', '   ')
            ->call('submit')
            ->assertSet('state', 'idle');
    }

    public function test_proof_completes_the_delivery(): void {
        Native::test(Proof::class, params: ['resi' => 'AJ-FRZ-0012345'])
            ->call('chooseRelation', 'Keluarga Serumah')
            ->assertSet('relation', 'Keluarga Serumah')
            ->call('submit')
            ->assertSet('state', 'done')
            ->assertSee('Pengiriman Berhasil Selesai');
    }

    public function test_issue_reports_what_went_wrong(): void {
        Native::test(Issue::class, params: ['resi' => 'AJ-FRZ-0012345'])
            ->assertSee('Lapor Masalah')
            ->assertSee('Kendala Lapangan')
            ->call('submit')
            ->assertSet('state', 'done')
            ->assertSee('Laporan Terkirim');
    }

    /** The web view is gone; nothing may reintroduce it. */
    public function test_no_screen_renders_a_web_view(): void {
        $screens = [Tasks::class, History::class, Profile::class, Delivery::class, Proof::class, Issue::class];

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
