<?php

namespace PictaStudio\Venditio\Actions\PriceListPrices;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PictaStudio\Venditio\Models\PriceListPrice;

use function PictaStudio\Venditio\Helpers\Functions\query;

class UpsertMultiplePriceListPrices
{
    public function handle(array $prices): Collection
    {
        return DB::transaction(function () use ($prices): Collection {
            $upsertedPrices = new Collection;
            $defaultPriceListPriceIdsByProduct = [];

            foreach ($prices as $pricePayload) {
                $identifiers = [
                    'product_id' => (int) $pricePayload['product_id'],
                    'price_list_id' => (int) $pricePayload['price_list_id'],
                ];

                $attributes = [
                    'price' => $pricePayload['price'],
                ];

                foreach (['purchase_price', 'price_includes_tax', 'is_default', 'metadata'] as $attribute) {
                    if (!array_key_exists($attribute, $pricePayload)) {
                        continue;
                    }

                    $attributes[$attribute] = $pricePayload[$attribute];
                }

                /** @var PriceListPrice $priceListPrice */
                $priceListPrice = query('price_list_price')
                    ->withTrashed()
                    ->updateOrCreate($identifiers, $attributes);

                if ($priceListPrice->trashed()) {
                    $priceListPrice->restore();
                }

                $upsertedPrices->push($priceListPrice->refresh());

                if ($priceListPrice->is_default) {
                    $defaultPriceListPriceIdsByProduct[(int) $identifiers['product_id']] = $priceListPrice->getKey();
                }
            }

            foreach ($defaultPriceListPriceIdsByProduct as $productId => $defaultPriceListPriceId) {
                query('price_list_price')
                    ->where('product_id', $productId)
                    ->whereKeyNot($defaultPriceListPriceId)
                    ->update(['is_default' => false]);
            }

            $upsertedPrices->each(fn (PriceListPrice $priceListPrice): PriceListPrice => $priceListPrice->refresh());

            return $upsertedPrices;
        });
    }
}
