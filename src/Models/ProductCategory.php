<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nevadskiy\Tree\AsTree;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use PictaStudio\Venditio\Models\Scopes\{Active, InDateRange, Ordered};
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods, LogsActivity, ResolvesRouteBindingByIdOrSlug};
use Spatie\Sluggable\{HasSlug, SlugOptions};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductCategory extends Model implements TranslatableContract
{
    use AsTree;
    use HasDiscounts;
    use HasFactory;
    use HasHelperMethods;
    use HasSlug;
    use LogsActivity;
    use ResolvesRouteBindingByIdOrSlug;
    use SoftDeletes;
    use Translatable;

    public array $translatedAttributes = ['name', 'slug', 'abstract', 'description'];

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
            'show_in_menu' => 'boolean',
            'in_evidence' => 'boolean',
            'visible_from' => 'datetime',
            'visible_until' => 'datetime',
            'metadata' => 'json',
            'img_thumb' => 'json',
            'img_cover' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScopes([
            Ordered::class,
            Active::class,
            new InDateRange('visible_from', 'visible_until'),
        ]);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product'), 'product_category_product')
            ->withTimestamps();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
