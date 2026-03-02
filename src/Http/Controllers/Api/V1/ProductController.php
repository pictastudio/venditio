<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\Products\{CreateProduct, CreateProductVariants, UpdateProduct};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Product\{GenerateProductVariantsRequest, StoreProductRequest, UpdateProductRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductResource;
use PictaStudio\Venditio\Models\Product;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ProductController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Product::class);

        $includes = $this->resolveProductIncludes();
        $filters = request()->except('include');
        $query = query('product')->with($this->productRelationsForIncludes($includes));

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

        return ProductResource::make($product->load($this->productRelationsForIncludes($includes)));
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

    protected function resolveProductIncludes(): array
    {
        $rawIncludes = request()->query('include', []);

        $includes = collect(is_array($rawIncludes) ? $rawIncludes : [$rawIncludes])
            ->flatMap(
                fn (mixed $include) => is_string($include) ? explode(',', $include) : []
            )
            ->map(fn (string $include) => mb_trim($include))
            ->filter(fn (string $include) => filled($include))
            ->unique()
            ->values()
            ->all();

        $allowedIncludes = ['variants', 'variants_options_table'];

        if (config('venditio.price_lists.enabled', false)) {
            $allowedIncludes[] = 'price_lists';
        }

        $this->validateData([
            'include' => $includes,
        ], [
            'include' => ['array'],
            'include.*' => [
                'string',
                Rule::in($allowedIncludes),
            ],
        ]);

        return $includes;
    }

    protected function productRelationsForIncludes(array $includes): array
    {
        $relations = ['variantOptions.productVariant', 'inventory'];

        if (config('venditio.price_lists.enabled', false)) {
            $relations[] = 'priceListPrices.priceList';
        }

        if (in_array('variants', $includes, true) || in_array('variants_options_table', $includes, true)) {
            $relations[] = 'variants.variantOptions.productVariant';
            $relations[] = 'variants.inventory';

            if (config('venditio.price_lists.enabled', false)) {
                $relations[] = 'variants.priceListPrices.priceList';
            }
        }

        return $relations;
    }

    protected function productIndexValidationRules(): array
    {
        return [
            'include_variants' => [
                'sometimes',
                'boolean',
            ],
            'exclude_variants' => [
                'sometimes',
                'boolean',
            ],
        ];
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
