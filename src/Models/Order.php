<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Fluent;
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Order extends Model
{
    use HasDiscounts;
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
            'status' => config('venditio.order.status_enum'),
            'last_tracked_at' => 'datetime:Y-m-d H:i:s',
            'sub_total_taxable' => 'decimal:2',
            'sub_total_tax' => 'decimal:2',
            'sub_total' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'specific_weight' => 'decimal:2',
            'volumetric_weight' => 'decimal:2',
            'chargeable_weight' => 'decimal:2',
            'payment_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_final' => 'decimal:2',
            'addresses' => 'json',
            'shipping_method_data' => 'json',
            'shipping_zone_data' => 'json',
            'approved_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(resolve_model('user'));
    }

    public function shippingStatus(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_status'));
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_method'));
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(resolve_model('shipping_zone'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(resolve_model('order_line'));
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(resolve_model('return_request'));
    }

    protected function addresses(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => new Fluent(match (true) {
                is_array($value) => $value,
                is_string($value) => json_decode($value, true) ?? [],
                default => [],
            }),
        );
    }
}
