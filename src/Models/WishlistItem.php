<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PictaStudio\Venditio\Models\Traits\{HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class WishlistItem extends Model
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
            'sort_order' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('venditio.wishlists.tables.wishlist_items', parent::getTable());
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(resolve_model('wishlist'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(resolve_model('product'));
    }
}
