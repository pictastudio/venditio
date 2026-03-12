<?php

namespace PictaStudio\Venditio\Pipelines\CartLine\Pipes;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\ProductPriceResolverInterface;
use PictaStudio\Venditio\Dto\Contracts\CartLineDtoContract;

use function PictaStudio\Venditio\Helpers\Functions\query;

class FillProductInformations
{
    /**
     * In this task the input data is an array of line data
     * - ['product_id', 'qty']
     */
    public function __invoke(CartLineDtoContract $cartLineDto, Closure $next): Model
    {
        $cartLine = $cartLineDto->getCartLine();

        $product = $this->fetchProduct($cartLineDto);
        $resolvedPricing = app(ProductPriceResolverInterface::class)->resolve($product);
        $productData = $product->toArray();

        data_set($productData, 'inventory.price_includes_tax', (bool) ($resolvedPricing['price_includes_tax'] ?? false));
        data_set($productData, 'pricing.price_list', $resolvedPricing['price_list'] ?? null);
        data_set($productData, 'pricing.price_source', $resolvedPricing['price_source'] ?? null);
        data_set($productData, 'price_calculated', [
            'price' => (float) ($resolvedPricing['unit_price'] ?? 0),
            'price_final' => (float) ($resolvedPricing['unit_price'] ?? 0),
            'purchase_price' => isset($resolvedPricing['purchase_price']) ? (float) $resolvedPricing['purchase_price'] : null,
            'price_includes_tax' => (bool) ($resolvedPricing['price_includes_tax'] ?? false),
            'price_list' => $resolvedPricing['price_list'] ?? null,
            'price_source' => $resolvedPricing['price_source'] ?? null,
        ]);

        $cartLine->product()->associate($product);
        $currencyId = $this->resolveCurrencyIdForProduct($product);

        $cartLine->fill([
            'currency_id' => $currencyId,
            'unit_price' => $resolvedPricing['unit_price'] ?? 0,
            'purchase_price' => $resolvedPricing['purchase_price'] ?? null,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'qty' => $cartLineDto->getQty(),
            'product_data' => $productData,
        ]);

        return $next($cartLine);
    }

    private function fetchProduct(CartLineDtoContract $cartLineDto): Model
    {
        $productId = $cartLineDto->getPurchasableModelId();

        return query('product')
            ->with([
                'inventory',
                'categories',
                'brand',
                'productType',
                'variantOptions',
                'parent',
                'priceListPrices.priceList',
            ])
            ->firstWhere('id', $productId);
    }

    private function resolveCurrencyIdForProduct(Model $product): int
    {
        $currencyId = data_get($product, 'inventory.currency_id')
            ?? $this->resolveDefaultCurrencyId();

        if (blank($currencyId)) {
            throw ValidationException::withMessages([
                'currency_id' => ['No default currency configured.'],
            ]);
        }

        return (int) $currencyId;
    }

    private function resolveDefaultCurrencyId(): ?int
    {
        $defaultCurrency = query('currency')
            ->where('is_default', true)
            ->first();

        if ($defaultCurrency) {
            return (int) $defaultCurrency->getKey();
        }

        $fallbackCurrency = query('currency')->first();

        if (!$fallbackCurrency) {
            return null;
        }

        $fallbackCurrency->update(['is_default' => true]);

        return (int) $fallbackCurrency->getKey();
    }

    private function checkPayloadValidity(array $line): void
    {
        if (!Arr::has($line, 'product_id')) {
            throw new Exception('The key "product_id" is missing from the line data');
        }

        if (!Arr::has($line, 'qty')) {
            throw new Exception('The key "qty" is missing from the line data');
        }
    }
}
