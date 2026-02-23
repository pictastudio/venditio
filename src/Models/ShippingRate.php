<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use PictaStudio\Venditio\Events\{ShippingRateCreated, ShippingRateDeleted, ShippingRateUpdated};
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingRate extends Model
{
    use HasFactory;
    use HasHelperMethods;
    use SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'base_fee' => 'decimal:2',
            'min_order_subtotal' => 'decimal:2',
            'max_order_subtotal' => 'decimal:2',
            'estimated_delivery_min_days' => 'integer',
            'estimated_delivery_max_days' => 'integer',
            'metadata' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (self $rate) => event(new ShippingRateCreated($rate)));
        static::updated(fn (self $rate) => event(new ShippingRateUpdated($rate)));
        static::deleted(fn (self $rate) => event(new ShippingRateDeleted($rate)));
    }

    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_carrier'));
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_zone'));
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(resolve_model('shipping_rate_tier'))
            ->orderBy('sort_order')
            ->orderBy('from_weight_kg');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(resolve_model('cart'));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(resolve_model('order'));
    }
}
