<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GpsAction;
use App\Enums\GpsLevel;
use App\Enums\GpsLockStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A courier asking the hub to review a blocked GPS action. An APPROVED row for
 * the same package and action is what lets the courier's retry through.
 */
final class GpsLockRequest extends Model
{
    protected $table = 'gps_lock_requests';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'staff_id',
        'action',
        'level',
        'reasons',
        'latitude',
        'longitude',
        'status',
        'decided_by',
        'decision_reason',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_id' => 'integer',
            'staff_id' => 'integer',
            'action' => GpsAction::class,
            'level' => GpsLevel::class,
            'reasons' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'status' => GpsLockStatus::class,
            'decided_by' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === GpsLockStatus::PENDING;
    }
}
