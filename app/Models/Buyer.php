<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Buyer extends Model
{
    use HasFactory;

    protected $table = 'buyers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'address',
        'area',
        'address_type',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'address_type' => AddressType::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'buyer_id');
    }
}
