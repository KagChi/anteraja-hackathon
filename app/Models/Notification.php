<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Enums\RecipientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'recipient_type',
        'recipient_id',
        'template',
        'payload',
        'status',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_id' => 'integer',
            'recipient_type' => RecipientType::class,
            'recipient_id' => 'integer',
            'payload' => 'array',
            'status' => NotificationStatus::class,
            'sent_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
