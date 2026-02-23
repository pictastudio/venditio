<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingZoneMember extends Model
{
    use HasFactory;
    use HasHelperMethods;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_zone'));
    }

    public function zoneable(): MorphTo
    {
        return $this->morphTo();
    }
}
