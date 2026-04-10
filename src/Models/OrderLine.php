<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class OrderLine extends Model
{
    use HasDiscounts;
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
            'free_gift_id' => 'integer',
            'is_free_gift' => 'boolean',
            'free_gift_data' => 'json',
            'discount_id' => 'integer',
            'discount_amount' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'unit_discount' => 'decimal:2',
            'unit_final_price' => 'decimal:2',
            'unit_final_price_tax' => 'decimal:2',
            'unit_final_price_taxable' => 'decimal:2',
            'total_final_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'product_data' => 'json',
            'requested_return_qty' => 'integer',
            'returned_qty' => 'integer',
            'has_return_requests' => 'boolean',
            'is_returned' => 'boolean',
            'is_fully_returned' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(resolve_model('order'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(resolve_model('product'));
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(resolve_model('currency'));
    }

    public function freeGift(): BelongsTo
    {
        return $this->belongsTo(resolve_model('free_gift'));
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(resolve_model('discount'));
    }

    public function returnRequestLines(): HasMany
    {
        return $this->hasMany(resolve_model('return_request_line'));
    }
}
