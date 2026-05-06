<?php

namespace PictaStudio\Venditio\Actions\Products;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\ProductSkuGeneratorInterface;
use PictaStudio\Venditio\Models\Product;

use function PictaStudio\Venditio\Helpers\Functions\query;

class CreateProductVariants
{
    public function __construct(
        private readonly ProductSkuGeneratorInterface $productSkuGenerator,
    ) {}

    /**
     * Create variant products for the given product
     *
     * from productVariants and productVariantOptions compute the matrix of productVariantOptions to create variants
     *
     * @param  array  $productVariants  associative array where the key is the productVariant id and the value is the array of productVariantOptions
     */
    public function execute(Product $product, array $productVariants): array
    {
        if ($product->parent_id) {
            throw ValidationException::withMessages([
                'product' => 'Variants can be created only from a base product.',
            ]);
        }

        if (!$product->product_type_id) {
            throw ValidationException::withMessages([
                'product_type_id' => 'The product must have a product_type_id to create variants.',
            ]);
        }

        $variantPayloads = collect($productVariants)->values();
        $variantIds = $variantPayloads->pluck('variant_id')->unique()->values();
        $optionIds = $variantPayloads->pluck('option_ids')->flatten()->unique()->values();

        if ($variantIds->count() !== $variantPayloads->count()) {
            throw ValidationException::withMessages([
                'variants' => 'Variant ids must be unique.',
            ]);
        }

        $variants = query('product_variant')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        if ($variants->count() !== $variantIds->count()) {
            throw ValidationException::withMessages([
                'variants' => 'One or more product variants were not found.',
            ]);
        }

        if ($variants->contains(fn ($variant) => $variant->product_type_id !== $product->product_type_id)) {
            throw ValidationException::withMessages([
                'variants' => 'All variants must belong to the same product type as the product.',
            ]);
        }

        $options = query('product_variant_option')
            ->whereIn('id', $optionIds)
            ->get()
            ->keyBy('id');

        if ($options->count() !== $optionIds->count()) {
            throw ValidationException::withMessages([
                'variants' => 'One or more variant options were not found.',
            ]);
        }

        $optionSets = $variantPayloads->map(function (array $payload) use ($options) {
            $variantId = $payload['variant_id'];
            $optionIds = collect($payload['option_ids'] ?? [])
                ->unique()
                ->values();

            if ($optionIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'variants' => 'Each variant must include at least one option.',
                ]);
            }

            $resolved = $optionIds->map(fn (int $optionId) => $options->get($optionId));

            if ($resolved->contains(fn ($option) => $option === null)) {
                throw ValidationException::withMessages([
                    'variants' => 'One or more variant options were not found.',
                ]);
            }

            if ($resolved->contains(fn ($option) => $option->product_variant_id !== $variantId)) {
                throw ValidationException::withMessages([
                    'variants' => 'Variant options must belong to their variant.',
                ]);
            }

            return $resolved->values();
        });

        $combinations = $this->buildCombinations($optionSets->all());
        $existingSignatures = $this->getExistingSignatures($product);

        $created = new Collection;
        $restored = new Collection;
        $skipped = [];

        foreach ($combinations as $combination) {
            $signature = $this->signatureFor($combination);

            if (isset($existingSignatures[$signature])) {
                $existingVariant = $existingSignatures[$signature];

                if ($existingVariant->trashed()) {
                    $variant = $this->restoreVariantProduct($product, $existingVariant, $combination);
                    $restored->push($variant);
                    $existingSignatures[$signature] = $variant;

                    continue;
                }

                $skipped[] = $signature;

                continue;
            }

            $variant = $this->createVariantProduct($product, $combination);
            $created->push($variant);
            $existingSignatures[$signature] = $variant;
        }

        return [
            'created' => $created,
            'restored' => $restored,
            'skipped' => $skipped,
            'total' => count($combinations),
        ];
    }

    private function buildCombinations(array $optionSets): array
    {
        $combinations = [[]];

        foreach ($optionSets as $options) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($options as $option) {
                    $next[] = array_merge($combination, [$option]);
                }
            }

            $combinations = $next;
        }

        return $combinations;
    }

    private function getExistingSignatures(Product $product): array
    {
        return $product->variants()
            ->withoutGlobalScopes()
            ->with('variantOptions:id')
            ->get()
            ->mapWithKeys(function (Product $variant) {
                $signature = $variant->variantOptions
                    ->pluck('id')
                    ->sort()
                    ->implode('-');

                return [$signature => $variant];
            })
            ->all();
    }

    private function createVariantProduct(Product $product, array $options): Product
    {
        $excluded = config('venditio.product_variants.copy_attributes_exclude', [
            'id',
            'slug',
            'sku',
            'ean',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $columns = array_diff($product->getTableFillableColumns(), $excluded);
        $attributes = collect($columns)
            ->mapWithKeys(fn (string $column) => [$column => $product->getAttribute($column)])
            ->toArray();
        $attributes['parent_id'] = $product->getKey();
        $attributes['name'] = $this->buildVariantName($product, $options);
        $attributes['sku'] = $this->productSkuGenerator->forVariant($product, $options);

        $variant = $product->newInstance($attributes);
        $variant->save();

        $this->syncVariantRelations($product, $variant, $options);
        $this->copyInventoryPricing($product, $variant);

        return $variant->refresh();
    }

    private function restoreVariantProduct(Product $product, Product $variant, array $options): Product
    {
        $variant->restoreQuietly();

        $this->syncVariantRelations($product, $variant, $options);
        $this->copyInventoryPricing($product, $variant);

        return $variant->refresh();
    }

    private function syncVariantRelations(Product $product, Product $variant, array $options): void
    {
        if (config('venditio.product_variants.copy_categories', true)) {
            $categoryTable = $product->categories()->getRelated()->getTable();
            $variant->categories()->sync($product->categories()->pluck("{$categoryTable}.id")->all());
        }

        if (config('venditio.product_variants.copy_collections', true)) {
            $collectionTable = $product->collections()->getRelated()->getTable();
            $variant->collections()->sync($product->collections()->pluck("{$collectionTable}.id")->all());
        }

        $variant->variantOptions()->sync(collect($options)->pluck('id')->all());
    }

    private function copyInventoryPricing(Product $product, Product $variant): void
    {
        $sourceInventory = $product->inventory()->first();

        if (!$sourceInventory) {
            return;
        }

        $variantInventory = $variant->inventory()
            ->withoutGlobalScopes()
            ->firstOrCreate([
                'product_id' => $variant->getKey(),
            ]);

        if (method_exists($variantInventory, 'trashed') && $variantInventory->trashed()) {
            $variantInventory->restore();
        }

        $variantInventory->forceFill([
            'price' => $sourceInventory->price,
            'purchase_price' => $sourceInventory->purchase_price,
            'price_includes_tax' => $sourceInventory->price_includes_tax,
        ])->save();
    }

    private function buildVariantName(Product $product, array $options): string
    {
        $separator = config('venditio.product_variants.name_separator', ' / ');
        $suffixSeparator = config('venditio.product_variants.name_suffix_separator', ' - ');

        $optionLabel = collect($options)
            ->map(fn ($option) => $this->formatOptionLabel($option->name))
            ->filter()
            ->implode($separator);

        if ($optionLabel === '') {
            return $product->name;
        }

        return $product->name . $suffixSeparator . $optionLabel;
    }

    private function formatOptionLabel(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function signatureFor(array $options): string
    {
        return collect($options)
            ->pluck('id')
            ->sort()
            ->implode('-');
    }
}
