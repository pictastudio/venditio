<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use PictaStudio\Venditio\Models\Traits\{HasDiscounts, HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\{resolve_enum, resolve_model};

class Cart extends Model
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
            'status' => config('venditio.cart.status_enum'),
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(resolve_model('user'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(resolve_model('order'));
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
        return $this->hasMany(resolve_model('cart_line'));
    }

    #[Scope]
    public function processing(Builder $builder): Builder
    {
        return $builder->where('status', resolve_enum('cart_status')::getProcessingStatus());
    }

    #[Scope]
    public function active(Builder $builder): Builder
    {
        return $builder->where('status', resolve_enum('cart_status')::getActiveStatus());
    }

    #[Scope]
    public function converted(Builder $builder): Builder
    {
        return $builder->where('status', resolve_enum('cart_status')::getConvertedStatus());
    }

    #[Scope]
    public function cancelled(Builder $builder): Builder
    {
        return $builder->where('status', resolve_enum('cart_status')::getCancelledStatus());
    }

    #[Scope]
    public function abandoned(Builder $builder): Builder
    {
        return $builder->where('status', resolve_enum('cart_status')::getAbandonedStatus());
    }

    #[Scope]
    public function pending(Builder $builder): Builder
    {
        return $builder->whereIn('status', resolve_enum('cart_status')::getPendingStatuses());
    }

    #[Scope]
    public function completed(Builder $builder): Builder
    {
        return $builder->whereIn('status', resolve_enum('cart_status')::getCompletedStatuses());
    }

    #[Scope]
    public function inactive(Builder $builder): Builder
    {
        return $builder->whereIn('status', resolve_enum('cart_status')::getInactiveStatuses());
    }

    public function purge(): void
    {
        DB::transaction(function () {
            $this->releaseReservedStock();

            $this->lines()->delete();
            $this->status = resolve_enum('cart_status')::getCancelledStatus();
            $this->save();
            $this->delete();
        });
    }

    public function abandon(): void
    {
        DB::transaction(function () {
            $this->releaseReservedStock();
            $this->status = resolve_enum('cart_status')::getAbandonedStatus();
            $this->save();
        });
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

    private function releaseReservedStock(): void
    {
        $reservedQtyByProduct = $this->lines()
            ->selectRaw('product_id, SUM(qty) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        if ($reservedQtyByProduct->isEmpty()) {
            return;
        }

        $inventories = resolve_model('inventory')::query()
            ->whereIn('product_id', $reservedQtyByProduct->keys()->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        foreach ($reservedQtyByProduct as $productId => $qtyToRelease) {
            $inventory = $inventories->get((int) $productId);

            if (
                !$inventory instanceof Model
                || !(bool) ($inventory->getAttribute('manage_stock') ?? true)
            ) {
                continue;
            }

            $inventory->stock_reserved = max(0, (int) $inventory->stock_reserved - (int) $qtyToRelease);
            $inventory->save();
        }
    }
}
