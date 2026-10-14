<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfirmationResponse;
use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\ConditionCheck;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The internal hook n8n uses to attach a WhatsApp conversation to the package
 * whose tracking number the customer typed.
 */
final class WhatsappLookupTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'internal-lookup-test-token';

    private const RESI = 'AJ-FRZ-0099001';

    private const PHONE = '+6289611122233';

    private Staff $admin;

    private Staff $courier;

    private Buyer $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['coldchain.n8n.token' => self::TOKEN]);

        $this->admin = Staff::firstOrCreate(
            ['email' => 'admin.whatsapp.lookup@anteraja.id'],
            [
                'name' => 'Admin WhatsApp Lookup',
                'role' => StaffRole::ADMIN,
                'phone' => '+6281100000999',
                'password' => 'secret123',
                'is_active' => true,
            ],
        );

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.whatsapp.lookup@anteraja.id'],
            [
                'name' => 'Courier WhatsApp Lookup',
                'role' => StaffRole::COURIER,
                'phone' => '+6281200000999',
                'password' => 'secret123',
                'is_active' => true,
            ],
        );

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => self::PHONE],
            [
                'name' => 'Pembeli WhatsApp Lookup',
                'is_active' => true,
                'address' => 'Jl. Pengujian No. 1, Jakarta Selatan',
                'area' => 'Tebet',
            ],
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Lookup Frozen Test'],
            ['handling_notes' => 'Suhu -18°C beku terjaga.'],
        );
    }

    public function test_it_requires_the_shared_token(): void
    {
        $payload = ['phone' => '6289611122233', 'resi' => self::RESI];

        $this->postJson('/api/internal/whatsapp/lookup', $payload)
            ->assertUnauthorized();

        $this->withHeader('X-Anteraja-Token', 'not-the-token')
            ->postJson('/api/internal/whatsapp/lookup', $payload)
            ->assertUnauthorized();
    }

    public function test_it_refuses_to_answer_when_the_token_is_unconfigured(): void
    {
        config(['coldchain.n8n.token' => '']);

        $this->withHeader('X-Anteraja-Token', self::TOKEN)
            ->postJson('/api/internal/whatsapp/lookup', ['phone' => '6289611122233', 'resi' => self::RESI])
            ->assertStatus(503);
    }

    public function test_it_validates_its_input(): void
    {
        // Posted directly: the helper always supplies a resi.
        $this->withHeader('X-Anteraja-Token', self::TOKEN)
            ->postJson('/api/internal/whatsapp/lookup', ['phone' => '6289611122233'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('resi');
    }

    public function test_an_unknown_resi_is_not_found(): void
    {
        $this->lookup(['resi' => 'AJ-FRZ-0000000'])
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_a_resi_belonging_to_another_number_is_not_found(): void
    {
        $this->package();

        // Same resi, a phone that is not the buyer's.
        $this->lookup(['phone' => '6289900011122'])
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_it_reports_a_pending_schedule_confirmation(): void
    {
        $package = $this->package();

        ScheduleConfirmation::create([
            'package_id' => $package->id,
            'response' => ConfirmationResponse::PENDING,
            'proposed_scheduled_at' => now()->addDay(),
            'requested_at' => now(),
        ]);

        $response = $this->lookup()->assertOk()
            ->assertJson([
                'found' => true,
                'tracking_number' => self::RESI,
                'template' => 'schedule_request',
            ]);

        $this->assertIsString($response->json('client_token'));
        $this->assertNotSame('', (string) $response->json('client_token'));
        $this->assertStringStartsWith('http', (string) $response->json('client_url'));
        $this->assertNotSame('', (string) $response->json('status_label'));
        $this->assertNotSame('', (string) $response->json('window'));
    }

    /**
     * n8n calls this app over the in-cluster service name, and a link built
     * from that host is unreachable from a customer's phone.
     */
    public function test_links_never_inherit_the_callers_host(): void
    {
        $this->package();

        $response = $this->postJson('http://anteraja-hackathon-service/api/internal/whatsapp/lookup', [
            'phone' => '6289611122233',
            'resi' => self::RESI,
        ], ['X-Anteraja-Token' => self::TOKEN])->assertOk();

        $clientUrl = (string) $response->json('client_url');

        $this->assertStringStartsWith((string) config('app.url'), $clientUrl);
        $this->assertStringNotContainsString('anteraja-hackathon-service', $clientUrl);
    }

    public function test_it_reports_the_issue_menu_for_an_open_issue(): void
    {
        $package = $this->package(['status' => PackageStatus::ISSUE_REPORTED]);

        $this->issueReport($package);

        $this->lookup()
            ->assertOk()
            ->assertJson([
                'found' => true,
                'template' => 'issue_options',
            ]);
    }

    /** Every status maps to the step that is actually pending, or to none. */
    public function test_it_maps_every_status_to_the_pending_step(): void
    {
        $expected = [
            PackageStatus::CREATED->value => 'none',
            PackageStatus::AWAITING_CONFIRMATION->value => 'schedule_request',
            PackageStatus::RESCHEDULE_REQUESTED->value => 'none',
            PackageStatus::CONFIRMED->value => 'none',
            PackageStatus::COURIER_ASSIGNED->value => 'none',
            PackageStatus::CHECKED->value => 'none',
            PackageStatus::IN_DELIVERY->value => 'in_delivery',
            PackageStatus::ISSUE_REPORTED->value => 'none',
            PackageStatus::RECEIVED->value => 'none',
            PackageStatus::RETURNED->value => 'none',
            PackageStatus::CLOSED->value => 'none',
        ];

        // One package per status: the database guards status transitions, so a
        // case cannot be reached by updating a single row.
        $index = 0;

        foreach ($expected as $status => $template) {
            $index += 1;
            $resi = 'AJ-FRZ-9'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);

            $this->package(['status' => $status], $resi);

            $this->lookup(['resi' => $resi])
                ->assertOk()
                ->assertJson([
                    'found' => true,
                    'tracking_number' => $resi,
                    'template' => $template,
                ]);
        }
    }

    /** A returned parcel the buyer may replace asks them to start that flow. */
    public function test_it_reports_the_replacement_step_after_a_return_decision(): void
    {
        $package = $this->package(['status' => PackageStatus::RETURNED]);

        $this->issueReport($package, IssueDecision::RETURN);

        $this->lookup()
            ->assertOk()
            ->assertJson([
                'found' => true,
                'template' => 'issue_decision',
            ]);
    }

    /** A returned parcel with a non-return decision still offers the menu. */
    public function test_it_reports_the_issue_menu_when_the_decision_was_not_a_return(): void
    {
        $package = $this->package(['status' => PackageStatus::RETURNED]);

        $this->issueReport($package, IssueDecision::RESEND);

        $this->lookup()
            ->assertOk()
            ->assertJson([
                'found' => true,
                'template' => 'issue_options',
            ]);
    }

    /** A confirmation already answered leaves nothing for the buyer to do. */
    public function test_a_confirmation_that_is_no_longer_pending_has_nothing_to_do(): void
    {
        $package = $this->package();

        ScheduleConfirmation::create([
            'package_id' => $package->id,
            'response' => ConfirmationResponse::READY,
            'proposed_scheduled_at' => now()->addDay(),
            'responded_at' => now(),
            'requested_at' => now(),
        ]);

        $this->lookup()
            ->assertOk()
            ->assertJson([
                'found' => true,
                'template' => 'none',
            ]);
    }

    public function test_a_delivered_package_has_nothing_pending(): void
    {
        $this->package(['status' => PackageStatus::RECEIVED]);

        $this->lookup()
            ->assertOk()
            ->assertJson([
                'found' => true,
                'template' => 'none',
            ]);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function lookup(array $overrides = []): TestResponse
    {
        return $this->withHeader('X-Anteraja-Token', self::TOKEN)
            ->postJson('/api/internal/whatsapp/lookup', array_merge([
                'phone' => '6289611122233',
                'resi' => self::RESI,
            ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function package(array $attributes = [], ?string $resi = null): Package
    {
        /** @var Package $package */
        $package = Package::create(array_merge([
            'buyer_id' => $this->buyer->id,
            'created_by' => $this->admin->id,
            'tracking_number' => $resi ?? self::RESI,
            'status' => PackageStatus::AWAITING_CONFIRMATION,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'weight_kg' => 1.5,
        ], $attributes))->refresh();

        PackageItem::create([
            'package_id' => $package->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        return $package;
    }

    private function issueReport(Package $package, ?IssueDecision $decision = null): IssueReport
    {
        $check = ConditionCheck::create([
            'package_id' => $package->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'temperature_c' => -18.0,
            'notes' => 'Kemasan bocor saat transit.',
        ]);

        return IssueReport::create([
            'check_id' => $check->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'Kemasan bocor saat transit di hub pendingin.',
            'photo_url' => 'https://anteraja.id/proof.jpg',
            'decision' => $decision,
            'decision_reason' => $decision !== null ? 'Keputusan operasional.' : null,
            'decided_by' => $decision !== null ? $this->admin->id : null,
            'decided_at' => $decision !== null ? now() : null,
            'reported_at' => now()->subHour(),
        ]);
    }
}
