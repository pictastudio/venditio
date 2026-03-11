<?php

namespace PictaStudio\Venditio\Pipelines\Cart\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ReserveStock
{
    public function __invoke(Model $cart, Closure $next): Model
    {
        $lines = $cart->getRelation('lines');

        if (!$lines instanceof Collection) {
            $lines = collect($lines ?? []);
        }

        $targetQtyByProduct = $this->sumLinesByProduct($lines);
        $currentQtyByProduct = $cart->exists
            ? $this->getCurrentCartQtyByProduct($cart)
            : collect();

        $productIds = $targetQtyByProduct->keys()
            ->merge($currentQtyByProduct->keys())
            ->unique()
            ->filter()
            ->values();

        if ($productIds->isEmpty()) {
            return $next($cart);
        }

        $inventories = query('inventory')
            ->whereIn('product_id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        foreach ($productIds as $productId) {
            $targetQty = (int) ($targetQtyByProduct->get($productId, 0));
            $currentQty = (int) ($currentQtyByProduct->get($productId, 0));
            $delta = $targetQty - $currentQty;

            if ($delta === 0) {
                continue;
            }

            $inventory = $inventories->get($productId);

            if (!$inventory instanceof Model) {
                throw ValidationException::withMessages([
                    'lines' => ["Inventory not found for product [{$productId}]."],
                ]);
            }

            if (!$this->managesStock($inventory)) {
                continue;
            }

            $stock = (int) $inventory->stock;
            $stockReserved = (int) $inventory->stock_reserved;
            $stockAvailable = $stock - $stockReserved;

            if ($delta > 0 && $stockAvailable < $delta) {
                throw ValidationException::withMessages([
                    'lines' => ["Insufficient stock for product [{$productId}]. Requested {$delta}, available {$stockAvailable}."],
                ]);
            }

            $inventory->fill([
                'stock_reserved' => max(0, $stockReserved + $delta),
            ]);
            $inventory->save();
        }

        return $next($cart);
    }

    private function managesStock(Model $inventory): bool
    {
        return (bool) ($inventory->getAttribute('manage_stock') ?? true);
    }

    private function sumLinesByProduct(Collection $lines): Collection
    {
        return $lines
            ->filter(fn (mixed $line): bool => (bool) data_get($line, 'product_id'))
            ->groupBy(fn (mixed $line): int => (int) data_get($line, 'product_id'))
            ->map(fn (Collection $productLines): int => $productLines->sum(fn (mixed $line): int => (int) data_get($line, 'qty', 0)));
    }

    private function getCurrentCartQtyByProduct(Model $cart): Collection
    {
        return query('cart_line')
            ->where('cart_id', $cart->getKey())
            ->get(['product_id', 'qty'])
            ->groupBy('product_id')
            ->map(fn (Collection $productLines): int => $productLines->sum(fn (Model $line): int => (int) $line->qty));
    }
}
