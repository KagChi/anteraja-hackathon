<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'slug',
        'category',
        'description',
        'price_idr',
        'unit',
        'weight_kg',
        'image_path',
        'is_available',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_idr' => 'integer',
            'weight_kg' => 'float',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<PackageItem, $this>
     */
    public function packageItems(): HasMany
    {
        return $this->hasMany(PackageItem::class, 'product_id');
    }

    /** The storefront's order: sort_order first, then the oldest row. */
    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeInCatalogOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** A price is only meaningful in whole rupiah with no decimals. */
    public function priceLabel(): string
    {
        return 'Rp '.number_format((float) $this->price_idr, 0, ',', '.');
    }

    public function weightLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->weight_kg, 2, ',', '.'), '0'), ',').' kg';
    }
}
