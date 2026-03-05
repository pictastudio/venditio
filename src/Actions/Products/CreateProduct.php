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
        $tagIds = Arr::pull($payload, 'tag_ids', []);
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

        $this->validateTagProductTypeCompatibility($tagIds, $payload['product_type_id'] ?? null);

        /** @var Product $product */
        $product = resolve_model('product')::create($payload);

        if (!empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }

        if (!empty($tagIds)) {
            $product->tags()->sync($tagIds);
        }

        if (is_array($inventoryPayload)) {
            if (!array_key_exists('price', $inventoryPayload)) {
                $inventoryPayload['price'] = 0;
            }

            $product->inventory()->updateOrCreate([], $inventoryPayload);
        }

        return $product->refresh()->load(['inventory', 'variantOptions']);
    }

    private function validateTagProductTypeCompatibility(array $tagIds, mixed $productTypeId): void
    {
        if ($tagIds === []) {
            return;
        }

        $resolvedProductTypeId = is_numeric($productTypeId) ? (int) $productTypeId : null;
        $invalidTags = resolve_model('tag')::withoutGlobalScopes()
            ->whereKey($tagIds)
            ->whereNotNull('product_type_id')
            ->when(
                $resolvedProductTypeId !== null,
                fn ($query) => $query->where('product_type_id', '!=', $resolvedProductTypeId),
                fn ($query) => $query
                    ->whereNotNull('product_type_id')
            )
            ->pluck('id')
            ->values()
            ->all();

        if ($invalidTags === []) {
            return;
        }

        throw ValidationException::withMessages([
            'tag_ids' => [
                'The selected tags are not compatible with the product type: ' . implode(', ', $invalidTags),
            ],
        ]);
    }
}
