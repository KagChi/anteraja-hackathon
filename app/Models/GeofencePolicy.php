<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The geofence radius the hub tolerates for one service type, in metres.
 * Rows are created by the reference seeder and edited from the admin console.
 */
final class GeofencePolicy extends Model
{
    public const DEFAULT_RADIUS_M = [
        'REGULER' => 100,
        'INSTANT_FROZEN' => 30,
    ];

    /**
     * The bounds the admin console offers per segment. The database check
     * constraint is wider (10-1000); these are the values a hub operator is
     * allowed to pick between.
     */
    public const SEGMENTS = [
        'INSTANT_FROZEN' => [
            'label' => 'Anteraja Instant Frozen',
            'sla' => 'SLA 2-3 Jam',
            'description' => 'Toleransi ketat di titik koordinat penerima untuk kiriman instan.',
            'min' => 10,
            'max' => 100,
            'step' => 5,
        ],
        'REGULER' => [
            'label' => 'Anteraja Reguler',
            'sla' => 'SLA 1-2 Hari',
            'description' => 'Toleransi drop-off standar: gerbang cluster, lobi gedung, atau pos lingkungan.',
            'min' => 25,
            'max' => 500,
            'step' => 10,
        ],
    ];

    protected $table = 'geofence_policies';

    protected $primaryKey = 'service_type';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_type',
        'radius_m',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'radius_m' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
