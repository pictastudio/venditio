<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};
use PictaStudio\Venditio\Models\Traits\{HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class PriceList extends Model
{
    use HasFactory;
    use HasHelperMethods;
    use LogsActivity;
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
            'allow_discounts' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function priceListPrices(): HasMany
    {
        return $this->hasMany(resolve_model('price_list_price'));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product'), 'price_list_prices')
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
}
