<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Enums\AddressType;
use App\Enums\ConfirmationResponse;
use App\Enums\PackageSource;
use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\ServiceType;
use App\Models\Buyer;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Models\ScheduleConfirmation;
use App\Support\DisplayTime;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A shipment opened by the customer in the app rather than by the hub.
 *
 * It mirrors App\Actions\Packages\CreatePackage — schedule window, pending
 * confirmation, buyer notification — with two differences: the package has no
 * staff creator (source CLIENT_APP, created_by null), and the recipient block
 * doubles as the customer's own delivery profile, which is saved back onto the
 * buyer row so the next checkout is prefilled and the courier's geofence has
 * coordinates to work with.
 */
final class CreateClientOrder
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notifications,
    ) {}

    /**
     * @param  array{
     *     recipient: array{name: string, phone: string, address: string, area?: ?string, address_type?: ?string, latitude?: ?float, longitude?: ?float},
     *     service_type: string,
     *     scheduled_date: string,
     *     window_start: string,
     *     window_end: string,
     *     handling_instructions?: ?string,
     *     items: list<array{product_id: int|string, quantity: int|string}>
     * }  $data
     */
    public function execute(Buyer $buyer, array $data): Package
    {
        return DB::transaction(function () use ($buyer, $data): Package {
            $recipient = $data['recipient'];

            $buyer->fill([
                'name' => $recipient['name'],
                'phone' => $recipient['phone'],
                'address' => $recipient['address'],
                'area' => $recipient['area'] ?? null,
                'address_type' => AddressType::fromInput((string) ($recipient['address_type'] ?? '')) ?? AddressType::RESIDENTIAL,
            ]);

            if (isset($recipient['latitude'], $recipient['longitude'])) {
                $buyer->latitude = (float) $recipient['latitude'];
                $buyer->longitude = (float) $recipient['longitude'];
            }

            $buyer->save();

            // 1. Resolve the cart against the catalog. Only rows the storefront
            //    actually offers can be ordered, and weight/category come from
            //    the products rather than from the client.
            $lines = $this->resolveItems($data['items']);
            $weightKg = round(array_sum(array_map(
                static fn (array $line): float => $line['product']->weight_kg * $line['quantity'],
                $lines,
            )), 2);

            [$scheduledAt, $scheduledEndAt] = $this->schedule($data);

            // 2. Package (status CREATED; the FSM moves it to
            //    AWAITING_CONFIRMATION below so status_logs stays complete).
            $package = Package::create([
                'buyer_id' => $buyer->id,
                'created_by' => null,
                'source' => PackageSource::CLIENT_APP,
                'courier_id' => null,
                'status' => PackageStatus::CREATED,
                'package_category' => $lines[0]['product']->category ?? 'Frozen Food',
                'service_type' => ServiceType::from((string) $data['service_type']),
                'koli_count' => 1,
                'weight_kg' => max(0.1, $weightKg),
                'scheduled_at' => $scheduledAt,
                'scheduled_end_at' => $scheduledEndAt,
                'handling_instructions' => $data['handling_instructions'] ?? null,
            ]);

            // The tracking number is a PostgreSQL default, so re-read to get it.
            $package->refresh();

            foreach ($lines as $line) {
                PackageItem::create([
                    'package_id' => $package->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                ]);
            }

            ScheduleConfirmation::create([
                'package_id' => $package->id,
                'response' => ConfirmationResponse::PENDING,
                'proposed_scheduled_at' => $scheduledAt,
                'requested_at' => Carbon::now('UTC'),
            ]);

            $package = $this->transitioner->transition(
                package: $package,
                to: PackageStatus::AWAITING_CONFIRMATION,
                actor: null,
                attributes: ['handling_instructions' => $data['handling_instructions'] ?? null],
            );

            $this->notifications->queue(
                package: $package,
                type: RecipientType::BUYER,
                recipientId: $buyer->id,
                template: 'schedule_request',
                payload: [
                    'tracking_number' => $package->tracking_number,
                    'scheduled_at' => $package->scheduled_at?->toIso8601String(),
                    'scheduled_end_at' => $package->scheduled_end_at?->toIso8601String(),
                    'source' => PackageSource::CLIENT_APP->value,
                ],
            );

            return $package;
        });
    }

    /**
     * @param  list<array{product_id: int|string, quantity: int|string}>  $items
     * @return list<array{product: Product, quantity: int}>
     */
    private function resolveItems(array $items): array
    {
        $quantities = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $item['quantity'];
        }

        $products = Product::query()
            ->whereIn('id', array_keys($quantities))
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            if ($product === null) {
                continue;
            }

            $lines[] = ['product' => $product, 'quantity' => min(20, max(1, $quantity))];
        }

        if ($lines === []) {
            abort(response()->json([
                'message' => 'Produk yang dipilih sudah tidak tersedia.',
                'code' => 'PRODUCT_UNAVAILABLE',
            ], 422));
        }

        return $lines;
    }

    /**
     * The window is chosen in Jakarta time and stored in UTC, exactly like the
     * hub console does it.
     *
     * @param  array{scheduled_date: string, window_start: string, window_end: string}  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    private function schedule(array $data): array
    {
        $date = $data['scheduled_date'];
        $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$data['window_start']}", DisplayTime::DISPLAY_TIMEZONE);
        $end = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$data['window_end']}", DisplayTime::DISPLAY_TIMEZONE);

        $startUtc = $start !== false ? $start->setTimezone('UTC') : Carbon::now('UTC');
        $endUtc = $end !== false ? $end->setTimezone('UTC') : $startUtc->copy()->addHours(2);

        return [$startUtc, $endUtc];
    }
}
