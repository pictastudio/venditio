<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Models\Product;

class ProductOutOfStock
{
    public readonly Product $product;

    public readonly int $stock;

    public readonly int $stock_reserved;

    public readonly int $stock_available;

    public readonly ?int $stock_min;

    public function __construct(
        public readonly Model $inventory,
    ) {
        $this->product = $inventory->product;
        $this->stock = (int) $inventory->stock;
        $this->stock_reserved = (int) $inventory->stock_reserved;
        $this->stock_available = (int) $inventory->stock_available;
        $this->stock_min = $inventory->stock_min !== null ? (int) $inventory->stock_min : null;
    }
}
