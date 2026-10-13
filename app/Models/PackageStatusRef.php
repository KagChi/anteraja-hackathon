<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PackageStatus;
use Illuminate\Database\Eloquent\Model;

final class PackageStatusRef extends Model
{
    protected $table = 'package_statuses';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'label',
        'is_waiting',
        'is_terminal',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => PackageStatus::class,
            'is_waiting' => 'boolean',
            'is_terminal' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
