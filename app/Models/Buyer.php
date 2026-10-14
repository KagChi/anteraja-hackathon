<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AddressType;
use App\Models\Builders\BuyerBuilder;
use App\Support\PhoneFormatter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class Buyer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'buyers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'password',
        'phone_verified_at',
        'is_active',
        'address',
        'area',
        'address_type',
        'latitude',
        'longitude',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'phone_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'address_type' => AddressType::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Normalize phone to E.164 (+62...) upon saving.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => ($value !== null && trim($value) !== '') ? PhoneFormatter::toE164Id($value) : $value,
        );
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'buyer_id');
    }

    /**
     * @param  Builder  $query
     * @return BuyerBuilder<static>
     */
    public function newEloquentBuilder($query): BuyerBuilder
    {
        return new BuyerBuilder($query);
    }
}
