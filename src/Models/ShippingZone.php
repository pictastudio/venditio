<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingZone extends Model
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
            'priority' => 'integer',
        ];
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('country'), 'shipping_zone_country')
            ->withTimestamps();
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('region'), 'shipping_zone_region')
            ->withTimestamps();
    }

    public function provinces(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('province'), 'shipping_zone_province')
            ->withTimestamps();
    }

    public function shippingMethodZones(): HasMany
    {
        return $this->hasMany(resolve_model('shipping_method_zone'));
    }

    public function shippingMethods(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('shipping_method'), 'shipping_method_zone')
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
