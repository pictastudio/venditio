<?php

namespace PictaStudio\Venditio\Actions\Products;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\ProductSkuGeneratorInterface;
use PictaStudio\Venditio\Models\Product;
use PictaStudio\Venditio\Models\Scopes\Active;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateProduct
{
    public function __construct(
        private readonly ProductSkuGeneratorInterface $productSkuGenerator,
    ) {}

    public function handle(array $payload): Product
    {
        $categoryIds = Arr::pull($payload, 'category_ids', []);
        $inventoryPayload = Arr::pull($payload, 'inventory');

        if (blank($payload['sku'] ?? null)) {
            $payload['sku'] = $this->productSkuGenerator->forProductPayload($payload);
        }

        if (blank($payload['product_type_id'] ?? null)) {
            $defaultProductType = resolve_model('product_type')::withoutGlobalScope(Active::class)
                ->where('is_default', true)
                ->first();
            if ($defaultProductType) {
                $payload['product_type_id'] = $defaultProductType->getKey();
            }
        }

        if (blank($payload['tax_class_id'] ?? null)) {
            $defaultTaxClass = resolve_model('tax_class')::where('is_default', true)->first();
            if ($defaultTaxClass) {
                $payload['tax_class_id'] = $defaultTaxClass->getKey();
            }
        }

        if (blank($payload['tax_class_id'] ?? null)) {
            throw ValidationException::withMessages([
                'tax_class_id' => ['tax_class_id is required when no default tax class is configured.'],
            ]);
        }

        /** @var Product $product */
        $product = resolve_model('product')::create($payload);

        if (!empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }

        if (is_array($inventoryPayload)) {
            if (!array_key_exists('price', $inventoryPayload)) {
                $inventoryPayload['price'] = 0;
            }

            $product->inventory()->updateOrCreate([], $inventoryPayload);
        }

        return $product->refresh()->load(['inventory', 'variantOptions']);
    }
}
