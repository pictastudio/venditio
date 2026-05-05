<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use PictaStudio\Venditio\Models\Traits\{HasHelperMethods, LogsActivity, ResolvesRouteBindingByIdOrSlug};
use Spatie\Sluggable\{HasSlug, SlugOptions};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Wishlist extends Model
{
    use HasFactory;
    use HasHelperMethods;
    use HasSlug;
    use LogsActivity;
    use ResolvesRouteBindingByIdOrSlug;
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
            'is_default' => 'boolean',
            'metadata' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $wishlist): void {
            $wishlist->items()->delete();
        });
    }

    public function getTable(): string
    {
        return config('venditio.wishlists.tables.wishlists', parent::getTable());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(resolve_model('user'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(resolve_model('wishlist_item'));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            resolve_model('product'),
            config('venditio.wishlists.tables.wishlist_items', 'wishlist_items')
        )
            ->withPivot(['id', 'notes', 'sort_order', 'created_at', 'updated_at', 'deleted_at'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
