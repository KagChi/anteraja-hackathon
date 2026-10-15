<?php

declare(strict_types=1);

namespace App\Services\Whatsapp;

use App\Models\WhatsappMessage;

/**
 * Riwayat pesan WhatsApp.
 *
 * Satu tempat yang menulis tabel riwayat: endpoint internal (pesan masuk dan
 * balasan untuk kurir/admin), endpoint internal untuk pesan keluar dari n8n,
 * dan dashboard admin yang membacanya.
 */
final class WhatsappMessageLog
{
    /**
     * Baris pesan masuk dengan id WhatsApp ini, bila sudah pernah tercatat.
     * Dipakai untuk mengenali balasan dobel.
     */
    public function findInbound(string $externalId): ?WhatsappMessage
    {
        if ($externalId === '') {
            return null;
        }

        return WhatsappMessage::query()
            ->where('direction', WhatsappMessage::IN)
            ->where('external_id', $externalId)
            ->first();
    }

    /**
     * Catat balasan kita untuk satu pesan masuk, plus pesan masuknya sendiri
     * bila belum ada.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordInbound(
        string $phone,
        string $body,
        ?string $externalId,
        ?string $reply = null,
        ?string $template = null,
        ?string $trackingNumber = null,
        ?string $role = null,
    ): WhatsappMessage {
        $existing = $externalId !== null && $externalId !== '' ? $this->findInbound($externalId) : null;

        if ($existing !== null) {
            $existing->update(array_filter([
                'reply_body' => $reply,
                'template' => $template,
                'tracking_number' => $trackingNumber,
                'role' => $role,
            ], fn ($value) => $value !== null));

            return $existing;
        }

        return WhatsappMessage::create([
            'phone' => $phone,
            'role' => $role,
            'direction' => WhatsappMessage::IN,
            'template' => $template,
            'body' => $body,
            'reply_body' => $reply,
            'status' => WhatsappMessage::RECEIVED,
            'tracking_number' => $trackingNumber,
            'external_id' => $externalId === '' ? null : $externalId,
        ]);
    }

    /**
     * Catat pesan keluar yang dikirim (atau gagal dikirim) workflow n8n.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordOutbound(
        string $phone,
        string $body,
        ?string $template = null,
        ?string $status = null,
        ?string $trackingNumber = null,
        ?string $role = null,
        ?string $externalId = null,
    ): ?WhatsappMessage {
        if ($externalId !== null && $externalId !== '') {
            $exists = WhatsappMessage::query()
                ->where('direction', WhatsappMessage::OUT)
                ->where('external_id', $externalId)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        return WhatsappMessage::create([
            'phone' => $phone,
            'role' => $role,
            'direction' => WhatsappMessage::OUT,
            'template' => $template,
            'body' => $body,
            'status' => in_array($status, [WhatsappMessage::SENT, WhatsappMessage::FAILED], true)
                ? $status
                : WhatsappMessage::SENT,
            'tracking_number' => $trackingNumber,
            'external_id' => $externalId === '' ? null : $externalId,
        ]);
    }
}
