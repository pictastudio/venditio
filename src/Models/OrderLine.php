<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function discount(): BelongsTo
    {
        return $this->belongsTo(resolve_model('discount'));
    }
}
