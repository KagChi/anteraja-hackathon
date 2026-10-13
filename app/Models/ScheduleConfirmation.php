<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConfirmationResponse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScheduleConfirmation extends Model
{
    use HasFactory;

    protected $table = 'schedule_confirmations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'response',
        'proposed_scheduled_at',
        'requested_at',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_id' => 'integer',
            'response' => ConfirmationResponse::class,
            'proposed_scheduled_at' => 'immutable_datetime',
            'requested_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
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
