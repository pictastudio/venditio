<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Events\{ProductOutOfStock, ProductStockBelowMinimum};
use PictaStudio\Venditio\Models\Traits\{HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Inventory extends Model
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
            'stock' => 'integer',
            'stock_reserved' => 'integer',
            'stock_available' => 'integer',
            'stock_min' => 'integer',
            'minimum_reorder_quantity' => 'integer',
            'reorder_lead_days' => 'integer',
            'manage_stock' => 'boolean',
            'price' => 'decimal:2',
            'price_includes_tax' => 'boolean',
            'purchase_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $inventory) {
            if (blank($inventory->currency_id)) {
                $inventory->currency_id = static::resolveDefaultCurrencyId();
            }

            if (blank($inventory->currency_id)) {
                throw ValidationException::withMessages([
                    'currency_id' => ['No default currency configured.'],
                ]);
            }

            $inventory->stock_available = (int) $inventory->stock - (int) $inventory->stock_reserved;
        });

        static::saved(function (self $inventory) {
            if (!$inventory->wasChanged('stock')) {
                return;
            }

            $previousStock = $inventory->getOriginal('stock');
            $currentStock = (int) $inventory->stock;
            $freshInventory = null;

            if ($previousStock !== null && (int) $previousStock > 0 && $currentStock === 0) {
                event(new ProductOutOfStock(
                    inventory: $freshInventory ??= $inventory->fresh(['product']) ?? $inventory,
                ));
            }

            $stockMin = $inventory->stock_min;

            if ($stockMin === null) {
                return;
            }

            if ($previousStock !== null && (int) $previousStock >= $stockMin && $currentStock < $stockMin) {
                event(new ProductStockBelowMinimum(
                    inventory: $freshInventory ??= $inventory->fresh(['product']) ?? $inventory,
                ));
            }
        });
    }

    private static function resolveDefaultCurrencyId(): ?int
    {
        $currencyModel = resolve_model('currency');
        $defaultCurrency = $currencyModel::query()
            ->where('is_default', true)
            ->first();

        if ($defaultCurrency) {
            return $defaultCurrency->getKey();
        }

        $fallbackCurrency = $currencyModel::query()->first();

        if (!$fallbackCurrency) {
            return null;
        }

        $fallbackCurrency->update(['is_default' => true]);

        return $fallbackCurrency->getKey();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(resolve_model('product'));
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(resolve_model('currency'));
    }
}
