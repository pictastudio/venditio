<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use PictaStudio\Venditio\Models\Scopes\Active;
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods};
use Spatie\Sluggable\{HasSlug, SlugOptions};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductType extends Model implements TranslatableContract
{
    use HasDiscounts;
    use HasFactory;
    use HasHelperMethods;
    use HasSlug;
    use SoftDeletes;
    use Translatable;

    public array $translatedAttributes = ['name', 'slug'];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(Active::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(resolve_model('product'));
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(resolve_model('product_variant'));
    }

    public function productCustomFields(): HasMany
    {
        return $this->hasMany(resolve_model('product_custom_field'));
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
