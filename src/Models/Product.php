<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasOne, MorphToMany};
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use PictaStudio\Venditio\Enums\Contracts\ProductStatus as ProductStatusContract;
use PictaStudio\Venditio\Models\Scopes\{Active, InDateRange, ProductStatusActive};
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods, LogsActivity, ResolvesRouteBindingByIdOrSlug};
use PictaStudio\Venditio\Support\ProductMedia;
use Spatie\Sluggable\{HasSlug, SlugOptions};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Product extends Model implements TranslatableContract
{
    use HasDiscounts;
    use HasFactory;
    use HasHelperMethods;
    use HasSlug;
    use LogsActivity;
    use ResolvesRouteBindingByIdOrSlug;
    use SoftDeletes;
    use Translatable;

    public array $translatedAttributes = [
        'name',
        'slug',
        'description',
        'description_short',
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => config('venditio.product.status_enum'),
            'active' => 'boolean',
            'new' => 'boolean',
            'in_evidence' => 'boolean',
            'visible_from' => 'datetime:Y-m-d H:i:s',
            'visible_until' => 'datetime:Y-m-d H:i:s',
            'images' => 'json',
            'files' => 'json',
            'measuring_unit' => config('venditio.product.measuring_unit_enum'),
            'qty_for_unit' => 'integer',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'weight' => 'decimal:2',
            'metadata' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScopes([
            Active::class,
            new InDateRange('visible_from', 'visible_until'),
            ProductStatusActive::class,
        ]);

        static::created(function (self $product) {
            if (!$product->inventory()->exists()) {
                $product->inventory()->create();
            }
        });

        static::saving(function (self $product): void {
            $normalizedMedia = ProductMedia::normalizeProductMedia(
                $product->getAttribute('images'),
                $product->getAttribute('files')
            );

            if ($product->getAttribute('images') !== null) {
                $product->setAttribute('images', $normalizedMedia['images']);
            }

            if ($product->getAttribute('files') !== null) {
                $product->setAttribute('files', $normalizedMedia['files']);
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(resolve_model('brand'));
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(resolve_model('product_type'));
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(resolve_model('tax_class'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product_category'), 'product_category_product')
            ->withTimestamps();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(resolve_model('tag'), 'taggable', 'taggables')
            ->withTimestamps();
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(resolve_model('inventory'));
    }

    public function priceLists(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('price_list'), 'price_list_prices')
            ->withPivot([
                'id',
                'price',
                'purchase_price',
                'price_includes_tax',
                'is_default',
                'metadata',
                'created_at',
                'updated_at',
            ]);
    }

    public function priceListPrices(): HasMany
    {
        return $this->hasMany(resolve_model('price_list_price'));
    }

    public function variantOptions(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product_variant_option'), 'product_configuration')
            ->withTimestamps();
    }

    public function scopeActiveStatuses(Builder $builder): Builder
    {
        $statusEnum = config('venditio.product.status_enum');

        if (!is_string($statusEnum) || !is_a($statusEnum, ProductStatusContract::class, true)) {
            return $builder;
        }

        $activeStatuses = collect($statusEnum::getActiveStatuses())
            ->map(fn (mixed $status) => is_object($status) && isset($status->value) ? $status->value : $status)
            ->filter(fn (mixed $status) => is_string($status) && filled($status))
            ->values()
            ->all();

        if ($activeStatuses === []) {
            return $builder;
        }

        return $builder->whereIn('status', $activeStatuses);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
