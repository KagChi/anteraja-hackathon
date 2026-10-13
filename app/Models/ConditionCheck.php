<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ConditionCheck extends Model
{
    use HasFactory;

    protected $table = 'condition_checks';

    public const string CREATED_AT = 'checked_at';

    public const ?string UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'courier_id',
        'is_normal',
        'checklist',
        'notes',
        'temperature_c',
        'checked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_id' => 'integer',
            'courier_id' => 'integer',
            'is_normal' => 'boolean',
            'checklist' => 'array',
            'temperature_c' => 'float',
            'checked_at' => 'immutable_datetime',
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
        return $this->belongsTo(Staff::class, 'courier_id');
    }

    /**
     * @return HasOne<IssueReport, $this>
     */
    public function issueReport(): HasOne
    {
        return $this->hasOne(IssueReport::class, 'check_id');
    }
}
