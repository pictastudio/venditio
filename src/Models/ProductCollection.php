<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, MorphToMany};
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use PictaStudio\Venditio\Models\Scopes\{Active, InDateRange};
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods, LogsActivity, ResolvesRouteBindingByIdOrSlug};
use PictaStudio\Venditio\Support\CatalogImage;
use Spatie\Sluggable\{HasSlug, SlugOptions};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductCollection extends Model implements TranslatableContract
{
    use HasDiscounts;
    use HasFactory;
    use HasHelperMethods;
    use HasSlug;
    use LogsActivity;
    use ResolvesRouteBindingByIdOrSlug;
    use SoftDeletes;
    use Translatable;

    public array $translatedAttributes = ['name', 'slug', 'description'];

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
            'visible_from' => 'datetime:Y-m-d H:i:s',
            'visible_until' => 'datetime:Y-m-d H:i:s',
            'metadata' => 'json',
            'images' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScopes([
            Active::class,
            new InDateRange('visible_from', 'visible_until'),
        ]);

        static::saving(function (self $collection): void {
            if ($collection->getAttribute('images') === null) {
                return;
            }

            $collection->setAttribute('images', CatalogImage::normalizeCollection($collection->getAttribute('images')));
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product'), 'product_collection_product')
            ->withTimestamps();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(resolve_model('tag'), 'taggable', 'taggables')
            ->withTimestamps();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
