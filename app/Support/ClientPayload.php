<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Translates customer API responses into the array shapes the native customer
 * blades already read.
 *
 * The blades were written against the prototype fixtures, so the field names
 * they expect (\`status_key\`, \`eta_date\`, timeline \`label\`/\`detail\`/\`active\`) are the
 * view model — this maps the API onto them and nothing else has to move.
 */
final class ClientPayload {
    /**
     * One catalog card (ProductResource).
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public static function product(array $product): array {
        return [
            'id' => (int) ($product['id'] ?? 0),
            'name' => (string) ($product['name'] ?? 'Produk Frozen'),
            'slug' => (string) ($product['slug'] ?? ''),
            'category' => (string) ($product['category'] ?? 'Frozen Food'),
            'description' => (string) ($product['description'] ?? ''),
            'price_label' => (string) ($product['price_label'] ?? 'Rp 0'),
            'price_idr' => (int) ($product['price_idr'] ?? 0),
            'unit' => (string) ($product['unit'] ?? 'pack'),
            'weight_label' => (string) ($product['weight_label'] ?? ''),
            'handling_notes' => (string) ($product['handling_notes'] ?? ''),
        ];
    }

    /**
     * The checkout receipt (OrderReceiptResource).
     *
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    public static function receipt(array $receipt): array {
        $items = is_array($receipt['items'] ?? null) ? $receipt['items'] : [];
        $recipient = is_array($receipt['recipient'] ?? null) ? $receipt['recipient'] : [];

        return [
            'resi' => (string) ($receipt['tracking_number'] ?? ''),
            'status' => (string) ($receipt['status_label'] ?? 'Menunggu Konfirmasi'),
            'service' => (string) ($receipt['service_label'] ?? 'Reguler'),
            'scheduled_label' => (string) ($receipt['scheduled_label'] ?? '-'),
            'weight_kg' => (string) ($receipt['weight_kg'] ?? ''),
            'total_idr' => (int) ($receipt['total_idr'] ?? 0),
            'total_label' => 'Rp '.number_format((int) ($receipt['total_idr'] ?? 0), 0, ',', '.'),
            'recipient' => (string) ($recipient['name'] ?? '-'),
            'address' => (string) ($recipient['address'] ?? ''),
            'note' => (string) ($receipt['handling_instructions'] ?? ''),
            'items' => array_values(array_map(
                static fn (array $item): array => [
                    'name' => (string) ($item['name'] ?? 'Produk'),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'line_total_label' => 'Rp '.number_format((int) ($item['line_total_idr'] ?? 0), 0, ',', '.'),
                ],
                array_filter($items, 'is_array'),
            )),
        ];
    }

    /**
     * One row of the Pesanan / Riwayat lists (OrderSummaryResource).
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public static function order(array $order): array {
        $status = is_array($order['status'] ?? null) ? $order['status'] : [];
        $completedLabel = $order['completed_label'] ?? null;

        return [
            'resi' => (string) ($order['resi'] ?? ''),
            'product' => (string) ($order['product'] ?? 'Paket Frozen'),
            'product_meta' => (string) ($order['product_meta'] ?? ''),
            'recipient' => (string) ($order['recipient'] ?? '-'),
            'recipient_area' => (string) ($order['recipient_area'] ?? ''),
            'status' => (string) ($status['label'] ?? ''),
            'status_key' => self::statusKey((string) ($status['code'] ?? '')),
            'eta_date' => (string) ($order['eta_date'] ?? ''),
            'eta_time' => (string) ($order['eta_time'] ?? (is_string($completedLabel) ? $completedLabel : '')),
            'action_required' => $order['action_required'] ?? null,
            'next_screen' => (string) ($order['next_screen'] ?? 'transit'),
        ];
    }

    /**
     * The tracking screen's order card, from a TrackingView payload.
     *
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    public static function trackingOrder(array $view): array {
        $cold = is_array($view['cold_chain'] ?? null) ? $view['cold_chain'] : [];
        [$etaDate, $etaTime] = self::splitSchedule((string) ($view['estimated_delivery'] ?? ''));

        return [
            'resi' => (string) ($view['tracking_number'] ?? ''),
            'status' => (string) ($view['status_label'] ?? ''),
            'status_key' => self::statusKey((string) ($view['status'] ?? '')),
            'product' => (string) ($view['product_name'] ?? 'Paket Frozen'),
            'product_meta' => (string) ($view['product_meta'] ?? ''),
            'eta_date' => $etaDate,
            'eta_time' => $etaTime,
            'recipient' => (string) ($view['recipient_name'] ?? '-'),
            'recipient_area' => (string) ($view['recipient_area'] ?? ''),
            'courier' => (string) ($view['courier_name'] ?? '-'),
            'courier_box' => (string) ($view['cooler_box_code'] ?? '-'),
            'courier_phone' => (string) ($view['courier_phone'] ?? ''),
            'temperature' => (string) ($cold['temperature_label'] ?? '-'),
            'temperature_state' => (string) ($cold['temperature_state'] ?? ''),
            'cold_chain' => (string) ($cold['chain_state'] ?? ''),
            'timeline' => self::timeline(is_array($view['timeline'] ?? null) ? $view['timeline'] : []),
            'issue_banner' => $view['issue_banner'] ?? null,
        ];
    }

    /**
     * The recipient/address card the schedule and issue screens share.
     *
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    public static function orderCard(array $view): array {
        $products = is_array($view['product_names'] ?? null) ? array_values($view['product_names']) : [];

        return [
            'resi' => (string) ($view['tracking_number'] ?? ''),
            'recipient' => (string) ($view['recipient_name'] ?? '-'),
            'courier' => (string) ($view['courier_name'] ?? '-'),
            'address' => (string) ($view['recipient_address'] ?? ''),
            'product' => (string) ($products[0] ?? 'Paket Frozen'),
        ];
    }

    /**
     * The schedule card, from a ScheduleView payload.
     *
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    public static function schedule(array $view): array {
        $cargo = is_array($view['cargo_items'] ?? null) ? ($view['cargo_items'][0] ?? []) : [];
        $cargo = is_array($cargo) ? $cargo : [];
        [$windowDate, $window] = self::splitSchedule((string) ($view['scheduled_delivery'] ?? ''));

        return [
            'status' => (string) ($view['status_label'] ?? ''),
            'cold_badge' => (string) ($view['cold_guaranteed_badge'] ?? '-18°C Terjamin'),
            'product_category' => (string) ($cargo['category'] ?? 'Makanan Beku (Frozen Food)'),
            'product_name' => (string) ($cargo['name'] ?? 'Paket Frozen'),
            'product_meta' => (string) ($cargo['quantity_label'] ?? ''),
            'product_note' => (string) ($view['handling_note'] ?? ''),
            'window' => $window,
            'window_date' => $windowDate,
            'courier' => (string) ($view['courier_name'] ?? '-'),
            'courier_role' => (string) ($view['courier_badge'] ?? 'Kurir Khusus Frozen Anteraja'),
            'priority' => ! empty($view['has_cold_priority']) ? 'Prioritas Dingin' : 'Reguler',
            'notice_title' => 'Penting Sebelum Konfirmasi',
            'notice' => (string) ($view['handling_note'] ?? ''),
            'can_action' => (bool) ($view['can_action'] ?? false),
            'available_slots' => is_array($view['available_slots'] ?? null) ? array_values($view['available_slots']) : [],
        ];
    }

    /**
     * The handling-history card on the issue screen, from an IssueView.
     *
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    public static function issue(array $view): array {
        $coldChip = $view['cold_chain_chip'] ?? null;

        return [
            'status_badge' => (string) ($view['status_label'] ?? 'Pengiriman Terkendala'),
            'temp_badge' => is_string($coldChip) && $coldChip !== '' ? $coldChip : 'Suhu belum tercatat',
            'problem_label' => 'Laporan Masalah Fisik',
            'problem' => (string) ($view['issue_summary'] ?? ''),
            'decision_label' => 'Keputusan Admin Operasional',
            'decision' => (string) ($view['decision_message'] ?? ''),
            'quality_label' => 'Pemeriksaan Kualitas Cold Chain',
            'quality' => (string) ($view['quality_inspection_note'] ?? 'Tim kontrol kualitas belum mencatat temuan tambahan.'),
            'history' => self::timeline(is_array($view['timeline'] ?? null) ? $view['timeline'] : []),
            'can_request_replacement' => (bool) ($view['can_request_replacement'] ?? false),
            'replacement_tracking_number' => $view['replacement_tracking_number'] ?? null,
        ];
    }

    /**
     * The customer profile card, from a BuyerProfileResource.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function profile(array $data): array {
        $stats = is_array($data['stats'] ?? null) ? $data['stats'] : [];

        return [
            'name' => (string) ($data['name'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'address' => (string) ($data['address'] ?? ''),
            'stats' => [
                ['label' => 'Pesanan aktif', 'value' => (string) ($stats['active_count'] ?? '0')],
                ['label' => 'Selesai', 'value' => (string) ($stats['completed_count'] ?? '0')],
                ['label' => 'Tepat waktu', 'value' => (string) ($stats['on_time_rate'] ?? '96%')],
            ],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @return list<array<string, mixed>>
     */
    public static function timeline(array $entries): array {
        $mapped = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $mapped[] = [
                'label' => (string) ($entry['title'] ?? ''),
                'time' => (string) ($entry['time'] ?? ''),
                'detail' => (string) ($entry['description'] ?? ''),
                'active' => (bool) ($entry['is_accent'] ?? false),
            ];
        }

        return $mapped;
    }

    /** Map an API package status code onto the pill key the blades colour by. */
    public static function statusKey(string $code): string {
        return match ($code) {
            'AWAITING_CONFIRMATION' => 'waiting',
            'ISSUE_REPORTED', 'RETURNED' => 'issue',
            'RECEIVED', 'CLOSED' => 'done',
            default => 'transit',
        };
    }

    /**
     * An API schedule string ("Kamis, 15 Oktober 2024, 13.00 - 15.00 WIB") is
     * one line; the blades show the date and the window apart.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitSchedule(string $value): array {
        $value = trim($value);

        if ($value === '') {
            return ['', ''];
        }

        $pos = strrpos($value, ', ');
        if ($pos === false) {
            return [$value, ''];
        }

        return [substr($value, 0, $pos), substr($value, $pos + 2)];
    }
}
