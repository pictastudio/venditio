<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use PictaStudio\Venditio\Models\Scopes\Ordered;
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductVariantOption extends Model implements TranslatableContract
{
    use HasFactory;
    use HasHelperMethods;
    use SoftDeletes;
    use Translatable;

    public array $translatedAttributes = ['name'];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(Ordered::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(resolve_model('product_variant'));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product'), 'product_configuration')
            ->withTimestamps();
    }

    public function getOrderingGroupKeyNames(): array
    {
        return ['product_variant_id'];
    }
}
