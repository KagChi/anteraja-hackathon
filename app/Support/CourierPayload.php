<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Translates courier API responses into the array shapes the native courier
 * blades already read. The courier endpoints already speak the screens' field
 * names in snake case; the only work here is the camelCase joins the
 * controllers emit and the handful of display defaults.
 */
final class CourierPayload {
    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    public static function task(array $task): array {
        return [
            'id' => (string) ($task['id'] ?? ''),
            'age' => (string) ($task['age'] ?? 'Baru saja'),
            'tracking' => (string) ($task['tracking'] ?? ''),
            'service' => (string) ($task['service'] ?? 'Reguler'),
            'window' => (string) ($task['window'] ?? 'Belum dijadwalkan'),
            'window_status' => (string) ($task['windowStatus'] ?? 'Tepat Waktu'),
            'recipient' => (string) ($task['recipient'] ?? '-'),
            'place_type' => (string) ($task['placeType'] ?? 'Rumah / Residensial'),
            'address' => (string) ($task['address'] ?? '-'),
            'instruction' => (string) ($task['instruction'] ?? 'Tidak ada instruksi khusus.'),
            'metrics' => is_array($task['metrics'] ?? null) ? $task['metrics'] : [],
            'status' => (string) ($task['status'] ?? ''),
            'stage' => (string) ($task['stage'] ?? ''),
            'distance' => 'Mengukur jarak…',
            'eta' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $delivery
     * @return array<string, mixed>
     */
    public static function delivery(array $delivery): array {
        return [
            'tracking' => (string) ($delivery['tracking'] ?? ''),
            'service' => (string) ($delivery['service'] ?? 'Paket Beku'),
            'recipient' => (string) ($delivery['recipient'] ?? '-'),
            'phone' => (string) ($delivery['phone'] ?? '-'),
            'phone_href' => (string) ($delivery['phoneHref'] ?? ''),
            'address' => (string) ($delivery['address'] ?? ''),
            'instruction' => (string) ($delivery['instruction'] ?? 'Tidak ada instruksi khusus.'),
            'map_label' => (string) ($delivery['mapLabel'] ?? $delivery['address'] ?? ''),
            'map_href' => (string) ($delivery['mapHref'] ?? ''),
            'status' => (string) ($delivery['status'] ?? ''),
            'stage' => (string) ($delivery['stage'] ?? ''),
            'can_depart' => (bool) ($delivery['canDepart'] ?? false),
            'can_arrive' => (bool) ($delivery['canArrive'] ?? false),
            'can_proof' => (bool) ($delivery['canProof'] ?? false),
            'can_report_issue' => (bool) ($delivery['canReportIssue'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function proofForm(array $form): array {
        $relations = [];
        $options = is_array($form['relationOptions'] ?? null) ? $form['relationOptions'] : [];

        foreach ($options as $option) {
            $label = is_array($option) ? ($option['label'] ?? null) : $option;
            if (is_string($label) && $label !== '') {
                $relations[] = $label;
            }
        }

        if ($relations === [] && is_array($form['relations'] ?? null)) {
            $relations = array_values(array_filter($form['relations'], 'is_string'));
        }

        return [
            'tracking' => (string) ($form['tracking'] ?? ''),
            'service' => (string) ($form['service'] ?? 'Reguler'),
            'recipient' => (string) ($form['recipient'] ?? ''),
            'photo_name' => 'bukti_serah_terima.jpg',
            'photo_meta' => '1 foto terlampir',
            'notes_placeholder' => (string) ($form['notesPlaceholder'] ?? 'Contoh: Diterima langsung, diletakkan di teras'),
            'relations' => $relations,
        ];
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function issueForm(array $form): array {
        $options = [];

        foreach (is_array($form['options'] ?? null) ? $form['options'] : [] as $option) {
            if (! is_array($option)) {
                continue;
            }

            $options[] = [
                'value' => (string) ($option['value'] ?? ''),
                'label' => (string) ($option['label'] ?? ''),
            ];
        }

        return [
            'tracking' => (string) ($form['tracking'] ?? ''),
            'recipient' => (string) ($form['recipient'] ?? ''),
            'reporter' => (string) ($form['reporter'] ?? ''),
            'photo_name' => 'foto_kendala.jpg',
            'photo_meta' => '1 foto terlampir',
            'note_limit' => (int) ($form['noteLimit'] ?? 200),
            'note' => '',
            'options' => $options,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function historyRow(array $row): array {
        return [
            'resi' => (string) ($row['resi'] ?? ''),
            'recipient' => (string) ($row['recipient'] ?? '-'),
            'completed_at' => (string) ($row['completedAt'] ?? '-'),
            'status' => (string) ($row['status'] ?? ''),
        ];
    }
}
