<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageSource;
use App\Enums\PackageStatus;
use App\Enums\ServiceType;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\Package;
use App\Models\Product;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The customer storefront: browse the catalog, sign up, and place an order that
 * the hub then sees in its shipment queue.
 */
final class ClientStorefrontTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('client-auth');
    }

    public function test_the_catalog_lists_the_seeded_frozen_goods(): void
    {
        $response = $this->getJson('/api/client/catalog');

        $response->assertOk()->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'category', 'price_idr', 'price_label', 'unit', 'weight_kg', 'weight_label', 'image_url']],
            'meta' => ['count'],
        ]);

        $names = array_column($response->json('data'), 'name');

        $this->assertContains('Nugget Ayam 1 kg', $names);
        $this->assertGreaterThanOrEqual(8, count($names));
    }

    public function test_a_product_can_be_opened_by_slug(): void
    {
        $this->getJson('/api/client/catalog/nugget-ayam-1kg')
            ->assertOk()
            ->assertJsonPath('data.slug', 'nugget-ayam-1kg')
            ->assertJsonPath('data.price_label', 'Rp 48.000');

        $this->getJson('/api/client/catalog/produk-hilang')->assertNotFound();
    }

    public function test_register_creates_a_buyer_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/client/auth/register', [
            'name' => 'Pelanggan Baru',
            'phone' => '081211100099',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'address' => 'Jl. Cikajang No. 7, Kebayoran Baru',
            'area' => 'Kebayoran Baru',
            'address_type' => 'rumah',
        ]);

        $response->assertCreated()->assertJsonStructure([
            'data' => ['token', 'token_type', 'expires_at', 'buyer' => ['id', 'name', 'phone', 'address', 'address_type']],
        ]);

        $buyer = Buyer::where('phone', '+6281211100099')->first();
        $this->assertNotNull($buyer);
        $this->assertSame('Pelanggan Baru', $buyer->name);
        $this->assertSame('RESIDENTIAL', $buyer->address_type?->value);
        $this->assertTrue(Hash::check('rahasia123', (string) $buyer->password));
    }

    public function test_register_rejects_a_phone_that_already_has_an_account(): void
    {
        Buyer::create([
            'name' => 'Sudah Ada',
            'phone' => '+6281211100077',
            'password' => 'rahasia123',
            'address' => 'Jl. Melawai No. 1',
            'is_active' => true,
        ]);

        $this->postJson('/api/client/auth/register', [
            'name' => 'Pelanggan Kembar',
            'phone' => '081211100077',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'address' => 'Jl. Melawai No. 2',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_a_signed_in_customer_can_check_out_and_the_hub_sees_the_order(): void
    {
        $buyer = $this->makeBuyer();
        $token = $buyer->createToken('client-mobile', ['client'])->plainTextToken;

        $nugget = Product::where('slug', 'nugget-ayam-1kg')->firstOrFail();
        $edamame = Product::where('slug', 'edamame-beku-500g')->firstOrFail();

        $response = $this->withToken($token)->postJson('/api/client/orders', [
            'recipient' => [
                'name' => 'Pelanggan Checkout',
                'phone' => '081299900011',
                'address' => 'Jl. Tebet Barat Dalam No. 5',
                'area' => 'Tebet, Jakarta Selatan',
                'address_type' => 'kantor',
                'latitude' => -6.229746,
                'longitude' => 106.848529,
            ],
            'service_type' => 'INSTANT_FROZEN',
            'scheduled_date' => Carbon::now('Asia/Jakarta')->addDay()->toDateString(),
            'window_start' => '13:00',
            'window_end' => '15:00',
            'handling_instructions' => 'Titip ke sekuriti lobi',
            'items' => [
                ['product_id' => $nugget->id, 'quantity' => 2],
                ['product_id' => $edamame->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()->assertJsonStructure([
            'message',
            'data' => ['tracking_number', 'status', 'status_label', 'source', 'service_type', 'scheduled_label', 'weight_kg', 'item_count', 'total_idr', 'items'],
        ]);

        $this->assertSame('CLIENT_APP', $response->json('data.source'));
        $this->assertSame(124000, $response->json('data.total_idr'));

        /** @var Package $package */
        $package = Package::where('tracking_number', $response->json('data.tracking_number'))->firstOrFail();

        $this->assertSame(PackageSource::CLIENT_APP, $package->source);
        $this->assertNull($package->created_by);
        $this->assertSame(PackageStatus::AWAITING_CONFIRMATION, $package->status);
        $this->assertSame(ServiceType::INSTANT_FROZEN, $package->service_type);
        $this->assertSame(2, $package->items()->count());
        $this->assertSame(2.5, (float) $package->weight_kg);
        $this->assertSame('COMMERCIAL', $package->buyer?->address_type?->value);

        // The hub queue can work the app queue on its own.
        $admin = Staff::where('role', StaffRole::ADMIN)->firstOrFail();
        $queue = $this->actingAs($admin)->getJson('/api/admin/shipments?source=CLIENT_APP&schedule=all&per_page=50');

        $queue->assertOk()->assertJsonFragment(['source' => 'CLIENT_APP', 'sourceBadge' => 'APP']);
    }

    public function test_checkout_requires_a_session_and_a_basket(): void
    {
        $this->postJson('/api/client/orders', [])->assertUnauthorized();

        $buyer = $this->makeBuyer();
        $token = $buyer->createToken('client-mobile', ['client'])->plainTextToken;

        $this->withToken($token)->postJson('/api/client/orders', [
            'recipient' => [
                'name' => 'Pelanggan Checkout',
                'phone' => '081299900011',
                'address' => 'Jl. Tebet Barat Dalam No. 5',
            ],
            'service_type' => 'REGULER',
            'scheduled_date' => Carbon::now('Asia/Jakarta')->addDay()->toDateString(),
            'window_start' => '13:00',
            'window_end' => '15:00',
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    private function makeBuyer(): Buyer
    {
        return Buyer::create([
            'name' => 'Pelanggan Checkout',
            'phone' => '+6281299900022',
            'password' => 'rahasia123',
            'address' => 'Jl. Tebet Barat Dalam No. 5',
            'area' => 'Tebet',
            'is_active' => true,
        ]);
    }
}
