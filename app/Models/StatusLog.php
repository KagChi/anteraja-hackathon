<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PackageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StatusLog extends Model
{
    use HasFactory;

    protected $table = 'status_logs';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_id' => 'integer',
            'from_status' => PackageStatus::class,
            'to_status' => PackageStatus::class,
            'changed_by' => 'integer',
            'changed_at' => 'immutable_datetime',
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
    public function changedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'changed_by');
    }

    /**
     * @return BelongsTo<PackageStatusRef, $this>
     */
    public function fromStatusRef(): BelongsTo
    {
        return $this->belongsTo(PackageStatusRef::class, 'from_status', 'code');
    }

    /**
     * @return BelongsTo<PackageStatusRef, $this>
     */
    public function toStatusRef(): BelongsTo
    {
        return $this->belongsTo(PackageStatusRef::class, 'to_status', 'code');
    }
}
