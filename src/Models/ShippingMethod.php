<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingMethod extends Model
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
            'flat_fee' => 'decimal:2',
            'volumetric_divisor' => 'decimal:2',
        ];
    }

    public function shippingMethodZones(): HasMany
    {
        return $this->hasMany(resolve_model('shipping_method_zone'));
    }

    public function shippingZones(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('shipping_zone'), 'shipping_method_zone')
            ->using(resolve_model('shipping_method_zone'))
            ->withPivot([
                'id',
                'active',
                'rate_tiers',
                'over_weight_price_per_kg',
                'created_at',
                'updated_at',
            ])
            ->withTimestamps();
    }
}
