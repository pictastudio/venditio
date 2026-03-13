<?php

namespace PictaStudio\Venditio\Pricing;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Contracts\ProductPriceResolverInterface;

class DefaultProductPriceResolver implements ProductPriceResolverInterface
{
    public function resolve(Model $product): array
    {
        if (!config('venditio.price_lists.enabled', false)) {
            return $this->fromInventory($product);
        }

        $priceListPrice = $this->resolvePriceListPrice($product);

        if (!$priceListPrice instanceof Model) {
            return $this->fromInventory($product);
        }

        $priceList = $priceListPrice->relationLoaded('priceList')
            ? $priceListPrice->getRelation('priceList')
            : $priceListPrice->priceList()->first();
        $priceListSummary = $priceList instanceof Model
            ? [
                'id' => $priceList->getKey(),
                'name' => (string) $priceList->getAttribute('name'),
            ]
            : null;

        return [
            'unit_price' => (float) ($priceListPrice->getAttribute('price') ?? 0),
            'purchase_price' => $priceListPrice->getAttribute('purchase_price') === null
                ? null
                : (float) $priceListPrice->getAttribute('purchase_price'),
            'price_includes_tax' => (bool) $priceListPrice->getAttribute('price_includes_tax'),
            'price_list' => $priceListSummary,
            'price_source' => [
                'type' => 'price_list',
                'price_list_price_id' => $priceListPrice->getKey(),
                'unit_price' => (float) ($priceListPrice->getAttribute('price') ?? 0),
                'purchase_price' => $priceListPrice->getAttribute('purchase_price') === null
                    ? null
                    : (float) $priceListPrice->getAttribute('purchase_price'),
                'price_includes_tax' => (bool) $priceListPrice->getAttribute('price_includes_tax'),
                'is_default' => (bool) $priceListPrice->getAttribute('is_default'),
                'metadata' => $priceListPrice->getAttribute('metadata'),
                'price_list' => $priceList instanceof Model
                    ? [
                        'id' => $priceList->getKey(),
                        'name' => (string) $priceList->getAttribute('name'),
                        'code' => $priceList->getAttribute('code'),
                        'active' => (bool) ($priceList->getAttribute('active') ?? true),
                        'description' => $priceList->getAttribute('description'),
                        'metadata' => $priceList->getAttribute('metadata'),
                    ]
                    : null,
            ],
        ];
    }

    protected function resolvePriceListPrice(Model $product): ?Model
    {
        if (!method_exists($product, 'priceListPrices')) {
            return null;
        }

        $priceListPrices = $product->relationLoaded('priceListPrices')
            ? $product->getRelation('priceListPrices')
            : $product->priceListPrices()->with('priceList')->get();

        return $priceListPrices
            ->firstWhere('is_default', true)
            ?? $priceListPrices->first();
    }

    protected function fromInventory(Model $product): array
    {
        if (!method_exists($product, 'inventory')) {
            return [
                'unit_price' => 0,
                'purchase_price' => null,
                'price_includes_tax' => true,
                'price_list' => null,
                'price_source' => [
                    'type' => 'inventory',
                    'unit_price' => 0.0,
                    'purchase_price' => null,
                    'price_includes_tax' => true,
                ],
            ];
        }

        $inventory = $product->relationLoaded('inventory')
            ? $product->getRelation('inventory')
            : $product->inventory()->first();

        return [
            'unit_price' => (float) ($inventory?->getAttribute('price') ?? 0),
            'purchase_price' => $inventory?->getAttribute('purchase_price') === null
                ? null
                : (float) $inventory?->getAttribute('purchase_price'),
            'price_includes_tax' => (bool) ($inventory?->getAttribute('price_includes_tax') ?? true),
            'price_list' => null,
            'price_source' => [
                'type' => 'inventory',
                'inventory_id' => $inventory?->getKey(),
                'unit_price' => (float) ($inventory?->getAttribute('price') ?? 0),
                'purchase_price' => $inventory?->getAttribute('purchase_price') === null
                    ? null
                    : (float) $inventory?->getAttribute('purchase_price'),
                'price_includes_tax' => (bool) ($inventory?->getAttribute('price_includes_tax') ?? true),
            ],
        ];
    }
}
