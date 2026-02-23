<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphToMany};
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Country extends Model
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

    public function taxClasses(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('tax_class'), 'country_tax_class')
            ->using(resolve_model('country_tax_class'))
            ->withTimestamps()
            ->withPivot('rate');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(resolve_model('currency'));
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(resolve_model('address'));
    }

    public function regions(): HasMany
    {
        return $this->hasMany(resolve_model('region'));
    }

    public function shippingZones(): MorphToMany
    {
        return $this->morphToMany(resolve_model('shipping_zone'), 'zoneable', 'shipping_zone_members')
            ->withTimestamps();
    }
}
