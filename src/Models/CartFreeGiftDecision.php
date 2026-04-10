<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Enums\CartFreeGiftDecisionType;
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CartFreeGiftDecision extends Model
{
    use HasFactory;
    use HasHelperMethods;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => CartFreeGiftDecisionType::class,
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(resolve_model('cart'));
    }

    public function freeGift(): BelongsTo
    {
        return $this->belongsTo(resolve_model('free_gift'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(resolve_model('product'));
    }
}
