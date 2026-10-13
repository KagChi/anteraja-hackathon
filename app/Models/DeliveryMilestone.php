<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryMilestoneEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryMilestone extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'delivery_milestones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'milestone' => DeliveryMilestoneEnum::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'courier_id');
    }
}
