<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class Staff extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'staff';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role',
        'phone',
        'email',
        'password',
        'is_active',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === StaffRole::ADMIN && $this->is_active;
    }

    public function isCourier(): bool
    {
        return $this->role === StaffRole::COURIER && $this->is_active;
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function assignedPackages(): HasMany
    {
        return $this->hasMany(Package::class, 'courier_id');
    }
}
