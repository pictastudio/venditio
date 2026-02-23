<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{HasMany, MorphToMany};
use PictaStudio\Venditio\Events\{ShippingZoneCreated, ShippingZoneDeleted, ShippingZoneUpdated};
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
            'is_fallback' => 'boolean',
            'metadata' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (self $zone) => event(new ShippingZoneCreated($zone)));
        static::updated(fn (self $zone) => event(new ShippingZoneUpdated($zone)));
        static::deleted(fn (self $zone) => event(new ShippingZoneDeleted($zone)));
    }

    public function rates(): HasMany
    {
        return $this->hasMany(resolve_model('shipping_rate'));
    }

    public function members(): HasMany
    {
        return $this->hasMany(resolve_model('shipping_zone_member'));
    }

    public function countries(): MorphToMany
    {
        return $this->morphedByMany(resolve_model('country'), 'zoneable', 'shipping_zone_members')
            ->withTimestamps();
    }

    public function regions(): MorphToMany
    {
        return $this->morphedByMany(resolve_model('region'), 'zoneable', 'shipping_zone_members')
            ->withTimestamps();
    }

    public function provinces(): MorphToMany
    {
        return $this->morphedByMany(resolve_model('province'), 'zoneable', 'shipping_zone_members')
            ->withTimestamps();
    }

    public function municipalities(): MorphToMany
    {
        return $this->morphedByMany(resolve_model('municipality'), 'zoneable', 'shipping_zone_members')
            ->withTimestamps();
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
