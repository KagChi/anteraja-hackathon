<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryMilestoneEnum;
use App\Enums\AddressType;
use App\Enums\DeliveryStage;
use App\Enums\PackageStatus;
use App\Enums\PackageSource;
use App\Enums\ServiceType;
use App\Support\PhoneFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

final class Package extends Model {
    use HasFactory;

    protected $table = 'packages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tracking_number',
        'buyer_id',
        'created_by',
        'courier_id',
        'cooler_box_code',
        'package_category',
        'weight_kg',
        'status',
        'scheduled_at',
        'scheduled_end_at',
        'handling_instructions',
        'resend_of_package_id',
        'closed_at',
        'service_type',
        'koli_count',
        'courier_accepted_at',
        'client_token',
        'source',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_area',
        'recipient_address_type',
        'recipient_latitude',
        'recipient_longitude',
        'delivery_pin_hash',
        'delivery_pin_sent_at',
        'delivery_pin_attempts',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'client_token',
        'delivery_pin_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'buyer_id' => 'integer',
            'created_by' => 'integer',
            'courier_id' => 'integer',
            'resend_of_package_id' => 'integer',
            'weight_kg' => 'float',
            'koli_count' => 'integer',
            'status' => PackageStatus::class,
            'service_type' => ServiceType::class,
            'source' => PackageSource::class,
            'scheduled_at' => 'immutable_datetime',
            'scheduled_end_at' => 'immutable_datetime',
            'courier_accepted_at' => 'datetime',
            'closed_at' => 'immutable_datetime',
            'recipient_address_type' => AddressType::class,
            'recipient_latitude' => 'float',
            'recipient_longitude' => 'float',
            'delivery_pin_sent_at' => 'immutable_datetime',
            'delivery_pin_attempts' => 'integer',
        ];
    }

    /**
     * The parcel's destination: the person at the door, who is not the buyer.
     *
     * The buyer is the customer who sends the parcel, so the recipient block is
     * stored per shipment — a different family member can be at the door next
     * time. Screens read this shape instead of the columns, and the courier's
     * geofence measures against these coordinates.
     *
     * @return Attribute<array{name: ?string, phone: ?string, address: ?string, area: ?string, address_type: ?AddressType, latitude: ?float, longitude: ?float}>
     */
    protected function recipient(): Attribute {
        return Attribute::make(get: fn (): array => [
            'name' => $this->recipient_name,
            'phone' => $this->recipient_phone,
            'address' => $this->recipient_address,
            'area' => $this->recipient_area,
            'address_type' => $this->recipient_address_type,
            'latitude' => $this->recipient_latitude,
            'longitude' => $this->recipient_longitude,
        ]);
    }

    /** Recipient numbers are stored the way buyer numbers are: E.164. */
    protected function recipientPhone(): Attribute {
        return Attribute::make(
            set: fn (?string $value) => ($value !== null && trim($value) !== '') ? PhoneFormatter::toE164Id($value) : $value,
        );
    }

    /**
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo {
        return $this->belongsTo(Buyer::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function creator(): BelongsTo {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function courier(): BelongsTo {
        return $this->belongsTo(Staff::class, 'courier_id');
    }

    /**
     * @return BelongsTo<PackageStatusRef, $this>
     */
    public function statusRef(): BelongsTo {
        return $this->belongsTo(PackageStatusRef::class, 'status', 'code');
    }

    /**
     * @return HasMany<PackageItem, $this>
     */
    public function items(): HasMany {
        return $this->hasMany(PackageItem::class, 'package_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany {
        return $this->belongsToMany(Product::class, 'package_items', 'package_id', 'product_id')
            ->withPivot('quantity');
    }

    /**
     * @return HasMany<ScheduleConfirmation, $this>
     */
    public function scheduleConfirmations(): HasMany {
        return $this->hasMany(ScheduleConfirmation::class, 'package_id');
    }

    /**
     * @return HasMany<ConditionCheck, $this>
     */
    public function conditionChecks(): HasMany {
        return $this->hasMany(ConditionCheck::class, 'package_id')->orderBy('checked_at', 'desc');
    }

    /**
     * @return HasOne<Delivery, $this>
     */
    public function delivery(): HasOne {
        return $this->hasOne(Delivery::class, 'package_id');
    }

    /**
     * @return HasMany<DeliveryMilestone, $this>
     */
    public function deliveryMilestones(): HasMany {
        return $this->hasMany(DeliveryMilestone::class, 'package_id')->orderBy('id', 'asc');
    }

    /**
     * @return HasOne<DeliveryMilestone, $this>
     */
    public function latestMilestone(): HasOne {
        return $this->hasOne(DeliveryMilestone::class, 'package_id')->latestOfMany('id');
    }

    public function deliveryStage(): DeliveryStage {
        $milestone = $this->latestMilestone?->milestone;
        if ($milestone === null) {
            return DeliveryStage::PENDING;
        }

        return match ($milestone) {
            DeliveryMilestoneEnum::DEPARTED => DeliveryStage::DEPARTED,
            DeliveryMilestoneEnum::ARRIVED => DeliveryStage::ARRIVED,
        };
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany {
        return $this->hasMany(Notification::class, 'package_id');
    }

    /**
     * @return HasMany<StatusLog, $this>
     */
    public function statusLogs(): HasMany {
        return $this->hasMany(StatusLog::class, 'package_id')->orderBy('changed_at', 'asc');
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function resendOf(): BelongsTo {
        return $this->belongsTo(Package::class, 'resend_of_package_id');
    }

    public function replacementPackage(): HasOne {
        return $this->hasOne(Package::class, 'resend_of_package_id');
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function resendPackages(): HasMany {
        return $this->hasMany(Package::class, 'resend_of_package_id');
    }

    /**
     * @return HasOneThrough<IssueReport, ConditionCheck, $this>
     */
    public function latestIssue(): HasOneThrough {
        return $this->hasOneThrough(
            IssueReport::class,
            ConditionCheck::class,
            'package_id', // ConditionCheck.package_id
            'check_id',   // IssueReport.check_id
            'id',         // Package.id
            'id'          // ConditionCheck.id
        )->latest('issue_reports.reported_at');
    }

    public function availableAdminActions(): string {
        if ($this->status === PackageStatus::ISSUE_REPORTED) {
            return 'issue_detail';
        }

        if ($this->status === PackageStatus::CREATED && $this->courier_id === null) {
            return 'assign_courier';
        }

        if ($this->status === PackageStatus::COURIER_ASSIGNED) {
            return 'request_confirmation';
        }

        return 'detail';
    }

    /**
     * @param  Builder<Package>  $query
     * @param  PackageSource|string  $source
     * @return Builder<Package>
     */
    public function scopeOfSource(Builder $query, PackageSource|string $source): Builder {
        return $query->where('source', $source instanceof PackageSource ? $source->value : $source);
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeWithCompletedAt(Builder $query): Builder {
        return $query->select('packages.*')
            ->selectRaw(
                "COALESCE(
                    deliveries.delivered_at,
                    (SELECT MAX(changed_at) FROM status_logs WHERE package_id = packages.id AND to_status = 'RETURNED')
                ) AS completed_at"
            )
            ->leftJoin('deliveries', 'deliveries.package_id', '=', 'packages.id');
    }
}
