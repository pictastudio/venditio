<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphToMany};
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Province extends Model
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

    public function region(): BelongsTo
    {
        return $this->belongsTo(resolve_model('region'));
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(resolve_model('municipality'));
    }

    public function shippingZones(): MorphToMany
    {
        return $this->morphToMany(resolve_model('shipping_zone'), 'zoneable', 'shipping_zone_members')
            ->withTimestamps();
    }
}
