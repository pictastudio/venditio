<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphToMany};
use Nevadskiy\Tree\AsTree;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use PictaStudio\Venditio\Models\Scopes\{Active, InDateRange, Ordered};
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods, LogsActivity, ResolvesRouteBindingByIdOrSlug};
use Spatie\Sluggable\{HasSlug, SlugOptions};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Tag extends Model implements TranslatableContract
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
            'visible_from' => 'datetime:Y-m-d H:i:s',
            'visible_until' => 'datetime:Y-m-d H:i:s',
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

    public function products(): MorphToMany
    {
        return $this->morphedByMany(resolve_model('product'), 'taggable', 'taggables')
            ->withTimestamps();
    }

    public function brands(): MorphToMany
    {
        return $this->morphedByMany(resolve_model('brand'), 'taggable', 'taggables')
            ->withTimestamps();
    }

    public function productCategories(): MorphToMany
    {
        return $this->morphedByMany(resolve_model('product_category'), 'taggable', 'taggables')
            ->withTimestamps();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(resolve_model('tag'), 'taggable', 'taggables')
            ->withTimestamps();
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(resolve_model('product_type'));
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
