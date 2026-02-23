<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use PictaStudio\Venditio\Events\{ShippingCarrierCreated, ShippingCarrierDeleted, ShippingCarrierUpdated};
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingCarrier extends Model
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
            'volumetric_divisor' => 'decimal:2',
            'weight_rounding_step_kg' => 'decimal:3',
            'metadata' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (self $carrier) => event(new ShippingCarrierCreated($carrier)));
        static::updated(fn (self $carrier) => event(new ShippingCarrierUpdated($carrier)));
        static::deleted(fn (self $carrier) => event(new ShippingCarrierDeleted($carrier)));
    }

    public function rates(): HasMany
    {
        return $this->hasMany(resolve_model('shipping_rate'));
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
