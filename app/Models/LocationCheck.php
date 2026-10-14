<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GpsAction;
use App\Enums\GpsLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One GPS assessment the server made for a courier action. Rows are evidence:
 * they are written whether or not the action was allowed to continue.
 */
final class LocationCheck extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'location_checks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'staff_id',
        'action',
        'latitude',
        'longitude',
        'accuracy_m',
        'level',
        'reasons',
        'distance_m',
        'radius_m',
        'inside_geofence',
        'applied',
        'override_id',
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
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_m' => 'float',
            'level' => GpsLevel::class,
            'reasons' => 'array',
            'distance_m' => 'integer',
            'radius_m' => 'integer',
            'inside_geofence' => 'boolean',
            'applied' => 'boolean',
            'override_id' => 'integer',
            'created_at' => 'immutable_datetime',
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
     * @return BelongsTo<GpsLockRequest, $this>
     */
    public function override(): BelongsTo
    {
        return $this->belongsTo(GpsLockRequest::class, 'override_id');
    }
}
