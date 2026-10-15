<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu pesan WhatsApp, masuk atau keluar, apa adanya — teks yang benar-benar
 * dikirim atau diterima, bukan template yang memproduksinya.
 */
final class WhatsappMessage extends Model
{
    public const IN = 'IN';

    public const OUT = 'OUT';

    public const RECEIVED = 'RECEIVED';

    public const SENT = 'SENT';

    public const FAILED = 'FAILED';

    protected $table = 'whatsapp_messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'role',
        'direction',
        'template',
        'body',
        'reply_body',
        'status',
        'tracking_number',
        'external_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
