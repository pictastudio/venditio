<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use PictaStudio\Venditio\Enums\{FreeGiftMode, FreeGiftProductMatchMode, FreeGiftSelectionMode};
use PictaStudio\Venditio\Models\Traits\{HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class FreeGift extends Model
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
            'mode' => FreeGiftMode::class,
            'selection_mode' => FreeGiftSelectionMode::class,
            'allow_decline' => 'boolean',
            'active' => 'boolean',
            'starts_at' => 'datetime:Y-m-d H:i:s',
            'ends_at' => 'datetime:Y-m-d H:i:s',
            'minimum_cart_subtotal' => 'decimal:2',
            'maximum_cart_subtotal' => 'decimal:2',
            'minimum_cart_quantity' => 'integer',
            'maximum_cart_quantity' => 'integer',
            'product_match_mode' => FreeGiftProductMatchMode::class,
        ];
    }

    public function qualifyingUsers(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('user'), 'free_gift_user')
            ->withTimestamps();
    }

    public function qualifyingProducts(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product'), 'free_gift_qualifying_product')
            ->withTimestamps();
    }

    public function giftProducts(): BelongsToMany
    {
        return $this->belongsToMany(resolve_model('product'), 'free_gift_product')
            ->withTimestamps();
    }

    public function cartDecisions(): HasMany
    {
        return $this->hasMany(resolve_model('cart_free_gift_decision'));
    }

    public function cartLines(): HasMany
    {
        return $this->hasMany(resolve_model('cart_line'));
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(resolve_model('order_line'));
    }
}
