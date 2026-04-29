<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\Products\{CreateProduct, CreateProductVariants, DeleteProductMedia, UpdateProduct, UploadProductVariantOptionMedia};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Product\{GenerateProductVariantsRequest, StoreProductRequest, UpdateProductRequest, UploadProductVariantOptionMediaRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductResource;
use PictaStudio\Venditio\Models\{Product, ProductVariantOption};

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class ProductController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Product::class);

        $includes = $this->resolveProductIncludes();
        $filters = request()->except('include');
        $query = query('product')->with($this->productRelationsForIncludes($includes));
        $this->applyProductIndexRelationFilters($query, $filters);

        if ($this->shouldExcludeVariantsFromIndex()) {
            $query->whereNull('parent_id');
        }

        return ProductResource::collection(
            $this->applyBaseFilters(
                $query,
                $filters,
                'product',
                $this->productIndexValidationRules()
            )
        );
    }

    public function store(StoreProductRequest $request)
    {
        $this->authorizeIfConfigured('create', Product::class);

        $includes = $this->resolveProductIncludes();

        $product = app(CreateProduct::class)
            ->handle($request->validated());

        return ProductResource::make($product->load($this->productRelationsForIncludes($includes)));
    }

    public function show(Product $product): JsonResource
    {
        $this->authorizeIfConfigured('view', $product);

        $includes = $this->resolveProductIncludes();
        $relations = $this->productRelationsForIncludes($includes);

        if (in_array('variants', $includes, true) && filled($product->parent_id)) {
            $relations = [
                ...$relations,
                ...$this->parentVariantRelationsForIncludes($relations),
            ];
        }

        return ProductResource::make($product->load($relations));
    }

    public function variants(Product $product): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('view', $product);

        $includes = $this->resolveProductIncludes();
        $filters = request()->except('include');

        $variants = $this->applyBaseFilters(
            query('product')
                ->where('parent_id', $product->getKey())
                ->with($this->productRelationsForIncludes($includes)),
            $filters,
            'product'
        );

        return ProductResource::collection($variants);
    }

    public function createVariants(GenerateProductVariantsRequest $request, Product $product, CreateProductVariants $action): JsonResponse
    {
        $this->authorizeIfConfigured('update', $product);

        $includes = $this->resolveProductIncludes();
        $result = $action->execute($product, $request->validated('variants'));
        $created = $result['created']->load($this->productRelationsForIncludes($includes));

        return response()->json([
            'data' => ProductResource::collection($created),
            'meta' => [
                'created' => $created->count(),
                'skipped' => count($result['skipped']),
                'total' => $result['total'],
            ],
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorizeIfConfigured('update', $product);

        $includes = $this->resolveProductIncludes();

        $product = app(UpdateProduct::class)
            ->handle($product, $request->validated());

        return ProductResource::make($product->load($this->productRelationsForIncludes($includes)));
    }

    public function destroy(Product $product)
    {
        $this->authorizeIfConfigured('delete', $product);

        $product->delete();

        return response()->noContent();
    }

    public function destroyMedia(Product $product, string $mediaId, DeleteProductMedia $action)
    {
        $this->authorizeIfConfigured('update', $product);

        $action->handle($product, $mediaId);

        return response()->noContent();
    }

    public function uploadVariantOptionMedia(
        UploadProductVariantOptionMediaRequest $request,
        Product $product,
        ProductVariantOption $productVariantOption,
        UploadProductVariantOptionMedia $action
    ): JsonResponse {
        $this->authorizeIfConfigured('update', $product);

        $updatedProducts = $action->handle($product, $productVariantOption, $request->validated());

        return response()->json([
            'data' => ProductResource::collection($updatedProducts),
            'meta' => [
                'updated' => $updatedProducts->count(),
                'product_variant_option_id' => $productVariantOption->getKey(),
            ],
        ]);
    }

    protected function resolveProductIncludes(): array
    {
        $allowedIncludes = [
            'brand',
            'categories',
            'collections',
            'price_breakdown',
            'tags',
            'product_type',
            'tax_class',
            'variants',
            'variants_options_table',
        ];

        if (config('venditio.price_lists.enabled', false)) {
            $allowedIncludes[] = 'price_lists';
        }

        return $this->resolveIncludes($this->allowedIncludesWithDiscounts($allowedIncludes));
    }

    protected function productRelationsForIncludes(array $includes): array
    {
        $relations = ['variantOptions.productVariant', 'variantOptions.variantProducts', 'inventory'];
        $includesCollection = collect($includes);

        if (config('venditio.price_lists.enabled', false)) {
            $relations[] = 'priceListPrices.priceList';
        }

        if ($includesCollection->contains('brand')) {
            $relations[] = 'brand';
        }

        if ($includesCollection->contains('categories')) {
            $relations[] = 'categories';
        }

        if ($includesCollection->contains('collections')) {
            $relations[] = 'collections';
        }

        $relations = [
            ...$relations,
            ...$this->discountRelationsForIncludes($includes),
        ];

        if ($includesCollection->contains('tags')) {
            $relations[] = 'tags';
        }

        if ($includesCollection->contains('product_type')) {
            $relations[] = 'productType';
        }

        if ($includesCollection->contains('tax_class')) {
            $relations[] = 'taxClass';
        }

        if (in_array('variants', $includes, true) || in_array('variants_options_table', $includes, true)) {
            $relations[] = 'variants.variantOptions.productVariant';
            $relations[] = 'variants.variantOptions.variantProducts';
            $relations[] = 'variants.inventory';

            if ($includesCollection->contains('brand')) {
                $relations[] = 'variants.brand';
            }

            if ($includesCollection->contains('categories')) {
                $relations[] = 'variants.categories';
            }

            if ($includesCollection->contains('collections')) {
                $relations[] = 'variants.collections';
            }

            $relations = [
                ...$relations,
                ...$this->discountRelationsForIncludes($includes, 'variants'),
            ];

            if ($includesCollection->contains('tags')) {
                $relations[] = 'variants.tags';
            }

            if ($includesCollection->contains('product_type')) {
                $relations[] = 'variants.productType';
            }

            if ($includesCollection->contains('tax_class')) {
                $relations[] = 'variants.taxClass';
            }

            if (config('venditio.price_lists.enabled', false)) {
                $relations[] = 'variants.priceListPrices.priceList';
            }
        }

        return collect($relations)
            ->unique()
            ->values()
            ->all();
    }

    protected function parentVariantRelationsForIncludes(array $relations): array
    {
        return collect($relations)
            ->filter(fn (string $relation): bool => $relation === 'variants' || str_starts_with($relation, 'variants.'))
            ->map(fn (string $relation): string => "parent.{$relation}")
            ->push('parent.variants')
            ->unique()
            ->values()
            ->all();
    }

    protected function productIndexValidationRules(): array
    {
        $productModel = app(resolve_model('product'));
        $productTable = method_exists($productModel, 'getTableName')
            ? $productModel->getTableName()
            : $productModel->getTable();
        $productKeyName = $productModel->getKeyName();

        $brandModel = app(resolve_model('brand'));
        $brandTable = method_exists($brandModel, 'getTableName')
            ? $brandModel->getTableName()
            : $brandModel->getTable();
        $brandKeyName = $brandModel->getKeyName();

        $categoryModel = app(resolve_model('product_category'));
        $categoryTable = method_exists($categoryModel, 'getTableName')
            ? $categoryModel->getTableName()
            : $categoryModel->getTable();
        $categoryKeyName = $categoryModel->getKeyName();

        $collectionModel = app(resolve_model('product_collection'));
        $collectionTable = method_exists($collectionModel, 'getTableName')
            ? $collectionModel->getTableName()
            : $collectionModel->getTable();
        $collectionKeyName = $collectionModel->getKeyName();

        $tagModel = app(resolve_model('tag'));
        $tagTable = method_exists($tagModel, 'getTableName')
            ? $tagModel->getTableName()
            : $tagModel->getTable();
        $tagKeyName = $tagModel->getKeyName();

        return [
            'include_variants' => [
                'sometimes',
                'boolean',
            ],
            'exclude_variants' => [
                'sometimes',
                'boolean',
            ],
            'ids' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'integer',
                Rule::exists($productTable, $productKeyName),
            ],
            'brand_ids' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'brand_ids.*' => [
                'integer',
                Rule::exists($brandTable, $brandKeyName),
            ],
            'category_ids' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'category_ids.*' => [
                'integer',
                Rule::exists($categoryTable, $categoryKeyName),
            ],
            'collection_ids' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'collection_ids.*' => [
                'integer',
                Rule::exists($collectionTable, $collectionKeyName),
            ],
            'tag_ids' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'tag_ids.*' => [
                'integer',
                Rule::exists($tagTable, $tagKeyName),
            ],
            'price' => [
                'sometimes',
                'numeric',
            ],
            'price_operator' => [
                'sometimes',
                'string',
                Rule::in(['>', '<', '>=', '<=', '=']),
            ],
        ];
    }

    protected function applyProductIndexRelationFilters(Builder $query, array &$filters): void
    {
        if (isset($filters['ids']) && is_array($filters['ids'])) {
            $query->whereKey($filters['ids']);
        }

        if (isset($filters['brand_ids']) && is_array($filters['brand_ids'])) {
            $query->whereIn('brand_id', $filters['brand_ids']);
        }

        if (isset($filters['category_ids']) && is_array($filters['category_ids'])) {
            $productModel = app(resolve_model('product'));
            $productTable = method_exists($productModel, 'getTableName')
                ? $productModel->getTableName()
                : $productModel->getTable();
            $productKey = $productModel->getKeyName();

            $categoriesRelation = $productModel->categories();
            $pivotTable = $categoriesRelation->getTable();
            $foreignPivotKey = $categoriesRelation->getForeignPivotKeyName();
            $relatedPivotKey = $categoriesRelation->getRelatedPivotKeyName();

            $query->whereIn(
                $productTable . '.' . $productKey,
                fn ($subQuery) => $subQuery
                    ->select($foreignPivotKey)
                    ->from($pivotTable)
                    ->whereIn($relatedPivotKey, $filters['category_ids'])
            );
        }

        if (isset($filters['collection_ids']) && is_array($filters['collection_ids'])) {
            $productModel = app(resolve_model('product'));
            $productTable = method_exists($productModel, 'getTableName')
                ? $productModel->getTableName()
                : $productModel->getTable();
            $productKey = $productModel->getKeyName();

            $collectionsRelation = $productModel->collections();
            $pivotTable = $collectionsRelation->getTable();
            $foreignPivotKey = $collectionsRelation->getForeignPivotKeyName();
            $relatedPivotKey = $collectionsRelation->getRelatedPivotKeyName();

            $query->whereIn(
                $productTable . '.' . $productKey,
                fn ($subQuery) => $subQuery
                    ->select($foreignPivotKey)
                    ->from($pivotTable)
                    ->whereIn($relatedPivotKey, $filters['collection_ids'])
            );
        }

        if (isset($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            $query->whereHas(
                'tags',
                fn (Builder $tagsQuery) => $tagsQuery->whereKey($filters['tag_ids'])
            );
        }

        if (array_key_exists('price', $filters)) {
            $priceOperator = $this->sanitizePriceOperator(
                is_string($filters['price_operator'] ?? null)
                    ? $filters['price_operator']
                    : '='
            );
            $price = (float) $filters['price'];

            $query->whereHas(
                'inventory',
                fn (Builder $inventoryQuery) => $inventoryQuery->where('price', $priceOperator, $price)
            );
        }

        if (($filters['sort_by'] ?? null) === 'price') {
            $this->applyPriceSort($query, $filters);

            unset($filters['sort_by']);
        }
    }

    protected function applyPriceSort(Builder $query, array $filters): void
    {
        $productModel = app(resolve_model('product'));
        $productTable = method_exists($productModel, 'getTableName')
            ? $productModel->getTableName()
            : $productModel->getTable();
        $productKeyName = $productModel->getKeyName();

        $inventoryModel = app(resolve_model('inventory'));
        $inventoryTable = method_exists($inventoryModel, 'getTableName')
            ? $inventoryModel->getTableName()
            : $inventoryModel->getTable();

        $sortDirection = $this->sanitizeSortDirection(
            is_string($filters['sort_dir'] ?? null)
                ? $filters['sort_dir']
                : 'asc'
        );

        $query->orderBy(
            $inventoryModel->newQuery()
                ->select($inventoryTable . '.price')
                ->whereColumn(
                    $inventoryTable . '.product_id',
                    $productTable . '.' . $productKeyName
                )
                ->limit(1),
            $sortDirection
        );
    }

    protected function sanitizePriceOperator(string $operator): string
    {
        return in_array($operator, ['>', '<', '>=', '<=', '='], true)
            ? $operator
            : '=';
    }

    protected function sanitizeSortDirection(string $sortDirection): string
    {
        $sortDirection = mb_strtolower($sortDirection);

        return in_array($sortDirection, ['asc', 'desc'], true)
            ? $sortDirection
            : 'asc';
    }

    protected function shouldExcludeVariantsFromIndex(): bool
    {
        $excludeVariants = (bool) config('venditio.product.exclude_variants_from_index', true);

        if (request()->has('include_variants')) {
            $excludeVariants = !request()->boolean('include_variants');
        }

        if (request()->has('exclude_variants')) {
            $excludeVariants = request()->boolean('exclude_variants');
        }

        return $excludeVariants;
    }
}
