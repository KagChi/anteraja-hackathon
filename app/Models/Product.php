<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'handling_notes',
    ];

    /**
     * @return HasMany<PackageItem, $this>
     */
    public function packageItems(): HasMany
    {
        return $this->hasMany(PackageItem::class, 'product_id');
    }
}
