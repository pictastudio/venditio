<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingRateTier extends Model
{
    use HasFactory;
    use HasHelperMethods;
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
            'from_weight_kg' => 'decimal:3',
            'to_weight_kg' => 'decimal:3',
            'additional_fee' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function shippingRate(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_rate'));
    }
}
