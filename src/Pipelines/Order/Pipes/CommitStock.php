<?php

namespace PictaStudio\Venditio\Pipelines\Order\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_enum};

class CommitStock
{
    public function __invoke(Model $order, Closure $next): Model
    {
        $lines = $order->getRelation('lines');

        if (!$lines instanceof Collection) {
            $lines = collect($lines ?? []);
        }

        $qtyByProduct = $lines
            ->filter(fn (mixed $line): bool => (bool) data_get($line, 'product_id'))
            ->groupBy(fn (mixed $line): int => (int) data_get($line, 'product_id'))
            ->map(fn (Collection $productLines): int => $productLines->sum(fn (mixed $line): int => (int) data_get($line, 'qty', 0)));

        if ($qtyByProduct->isNotEmpty()) {
            $inventories = query('inventory')
                ->whereIn('product_id', $qtyByProduct->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($qtyByProduct as $productId => $qty) {
                $inventory = $inventories->get((int) $productId);

                if (!$inventory instanceof Model) {
                    throw ValidationException::withMessages([
                        'cart_id' => ["Inventory not found for product [{$productId}]."],
                    ]);
                }

                if (!$this->managesStock($inventory)) {
                    continue;
                }

                $stock = (int) $inventory->stock;
                $stockReserved = (int) $inventory->stock_reserved;

                if ($stock < $qty) {
                    throw ValidationException::withMessages([
                        'cart_id' => ["Insufficient stock for product [{$productId}]. Requested {$qty}, available {$stock}."],
                    ]);
                }

                if ($stockReserved < $qty) {
                    throw ValidationException::withMessages([
                        'cart_id' => ["Reserved stock mismatch for product [{$productId}]. Reserved {$stockReserved}, required {$qty}."],
                    ]);
                }

                $inventory->fill([
                    'stock' => $stock - $qty,
                    'stock_reserved' => $stockReserved - $qty,
                ]);
                $inventory->save();
            }
        }

        $sourceCart = $order->getRelation('sourceCart');

        if ($sourceCart instanceof Model && $order->exists) {
            $sourceCart->fill([
                'status' => resolve_enum('cart_status')::getConvertedStatus(),
                'order_id' => $order->getKey(),
            ]);
            $sourceCart->save();
        }

        return $next($order);
    }

    private function managesStock(Model $inventory): bool
    {
        return (bool) ($inventory->getAttribute('manage_stock') ?? true);
    }
}
