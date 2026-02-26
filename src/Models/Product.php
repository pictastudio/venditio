<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasOne};
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use PictaStudio\Venditio\Models\Scopes\{Active, InDateRange};
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods, LogsActivity, ResolvesRouteBindingByIdOrSlug};
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
            'visible_from' => 'datetime',
            'visible_until' => 'datetime',
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
        ]);

        static::created(function (self $product) {
            if (!$product->inventory()->exists()) {
                $product->inventory()->create();
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
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product_category'), 'product_category_product')
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

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
