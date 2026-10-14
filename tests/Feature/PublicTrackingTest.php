<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Package;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The public resi lookup behind the landing page's "Lacak Pesananmu" panel.
 *
 * It answers without a session, so the test that matters most is the one that
 * proves the response carries no way to identify the recipient.
 */
final class PublicTrackingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_known_resi_returns_the_status_and_the_journey(): void
    {
        $response = $this->getJson('/api/track/AJ-FRZ-0012345');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'resi',
                'status' => ['code', 'label', 'tone'],
                'service',
                'category',
                'cold_chain',
                'destination_city',
                'schedule_label',
                'updated_label',
                'timeline' => [['label', 'time', 'detail', 'is_accent']],
            ],
        ]);

        $this->assertSame('AJ-FRZ-0012345', $response->json('data.resi'));
        $this->assertNotEmpty($response->json('data.timeline'));
        $this->assertSame('Beku -18°C', $response->json('data.cold_chain'));
    }

    public function test_the_response_never_identifies_the_recipient(): void
    {
        /** @var Package $package */
        $package = Package::where('tracking_number', 'AJ-FRZ-0012345')->with('buyer')->firstOrFail();
        $buyer = $package->buyer;

        $this->assertNotNull($buyer);

        $body = $this->getJson('/api/track/AJ-FRZ-0012345')->assertOk()->getContent();

        $this->assertStringNotContainsString((string) $buyer?->name, $body);
        $this->assertStringNotContainsString((string) $buyer?->phone, $body);
        $this->assertStringNotContainsString((string) $buyer?->address, $body);
        // The area is cut down to its last segment, so the street part of a
        // compound area never travels.
        $this->assertStringNotContainsString('Jl.', $body);
    }

    public function test_the_destination_is_only_the_district_or_city(): void
    {
        /** @var Package $package */
        $package = Package::where('tracking_number', 'AJ-FRZ-0012345')->with('buyer')->firstOrFail();
        $area = (string) $package->buyer?->area;
        $expected = trim(substr($area, strrpos($area, ',') === false ? 0 : strrpos($area, ',') + 1));

        $this->getJson('/api/track/AJ-FRZ-0012345')
            ->assertOk()
            ->assertJsonPath('data.destination_city', $expected);
    }

    public function test_the_lookup_ignores_letter_case(): void
    {
        $this->getJson('/api/track/aj-frz-0012345')
            ->assertOk()
            ->assertJsonPath('data.resi', 'AJ-FRZ-0012345');
    }

    public function test_an_unknown_resi_is_not_found(): void
    {
        $this->getJson('/api/track/AJ-FRZ-9999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'TRACKING_NOT_FOUND');
    }
}
