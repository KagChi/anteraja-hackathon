<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeliveryStage;
use App\Enums\RecipientRelation;
use App\Enums\ScheduleTiming;
use App\Support\ChecklistNormalizer;
use App\Support\GeoDistance;
use App\Support\PhoneFormatter;
use App\Support\RelativeTime;
use Carbon\Carbon;
use Tests\TestCase;

final class CourierUnitTest extends TestCase
{
    public function test_geodistance_haversine(): void
    {
        // Tebet to Monas (~7.5 km)
        $dist = GeoDistance::haversine(-6.229746, 106.848529, -6.175392, 106.827153);
        $this->assertGreaterThan(5.0, $dist);
        $this->assertLessThan(10.0, $dist);
    }

    public function test_geodistance_eta_minutes(): void
    {
        // 2.4 km at 12 km/h avg speed -> ceil(2.4 / 12 * 60) = 12 minutes
        $eta = GeoDistance::etaMinutes(2.4, 12.0);
        $this->assertSame(12, $eta);
    }

    public function test_phone_formatter(): void
    {
        $this->assertSame('+6281298765432', PhoneFormatter::toE164Id('0812-9876-5432'));
        $this->assertSame('+6281298765432', PhoneFormatter::toE164Id('6281298765432'));
        $this->assertSame('+6281298765432', PhoneFormatter::toE164Id('+6281298765432'));
    }

    public function test_relative_time(): void
    {
        $now = Carbon::now();
        $this->assertSame('baru saja', RelativeTime::diffForHumansId($now->copy()->subSeconds(30)));
        $this->assertSame('Masuk 2 mnt lalu', RelativeTime::diffForHumansId($now->copy()->subMinutes(2)));
        $this->assertSame('Masuk 1 jam lalu', RelativeTime::diffForHumansId($now->copy()->subHours(1)));
        $this->assertSame('Kemarin', RelativeTime::diffForHumansId($now->copy()->subDays(1)));
    }

    public function test_delivery_stage(): void
    {
        $this->assertSame('Menunggu Berangkat', DeliveryStage::PENDING->label());
        $this->assertSame('Sedang Diantar', DeliveryStage::DEPARTED->label());
        $this->assertSame('Tiba di Lokasi', DeliveryStage::ARRIVED->label());
    }

    public function test_checklist_normalizer(): void
    {
        $raw = [
            'packaging_damaged' => ['answer' => true, 'detail' => 'Bocor'],
            'unknown_item' => ['answer' => true],
            'ice_cream_melting' => ['answer' => false],
        ];

        $normalized = ChecklistNormalizer::normalize($raw);

        $this->assertArrayHasKey('packaging_damaged', $normalized);
        $this->assertArrayHasKey('ice_cream_melting', $normalized);
        $this->assertArrayNotHasKey('unknown_item', $normalized);
        $this->assertTrue($normalized['packaging_damaged']['answer']);
        $this->assertFalse($normalized['ice_cream_melting']['answer']);
    }

    public function test_recipient_relation_from_input(): void
    {
        $this->assertSame(RecipientRelation::SELF, RecipientRelation::fromInput('Penerima Sendiri'));
        $this->assertSame(RecipientRelation::SELF, RecipientRelation::fromInput('SELF'));
        $this->assertSame(RecipientRelation::FAMILY, RecipientRelation::fromInput('Keluarga Serumah'));
        $this->assertSame(RecipientRelation::COLLEAGUE, RecipientRelation::fromInput('Rekan Kerja'));
        $this->assertSame(RecipientRelation::SECURITY, RecipientRelation::fromInput('Satpam / Sekuriti'));
        $this->assertNull(RecipientRelation::fromInput('Orang Asing'));
    }

    public function test_schedule_timing(): void
    {
        $this->assertSame(ScheduleTiming::ON_TIME, ScheduleTiming::forWindow(Carbon::now()->addHours(2)));
        $this->assertSame(ScheduleTiming::URGENT, ScheduleTiming::forWindow(Carbon::now()->addMinutes(15)));
        $this->assertSame(ScheduleTiming::LATE, ScheduleTiming::forWindow(Carbon::now()->subMinutes(5)));
    }
}
