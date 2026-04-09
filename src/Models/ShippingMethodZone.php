<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, Pivot};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingMethodZone extends Pivot
{
    public $incrementing = true;

    protected $table = 'shipping_method_zone';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'rate_tiers' => 'json',
            'over_weight_price_per_kg' => 'decimal:2',
        ];
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_method'));
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_zone'));
    }
}
