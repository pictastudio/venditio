<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\ProductCollections\{CreateProductCollection, UpdateProductCollection};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ProductCollection\{StoreProductCollectionRequest, UpdateProductCollectionRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductCollectionResource;
use PictaStudio\Venditio\Models\ProductCollection;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ProductCollectionController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ProductCollection::class);

        $includes = $this->resolveProductCollectionIncludes();
        $filters = request()->except('include');

        return ProductCollectionResource::collection(
            $this->applyBaseFilters(
                query('product_collection')->with($this->productCollectionRelationsForIncludes($includes)),
                $filters,
                'product_collection'
            )
        );
    }

    public function store(StoreProductCollectionRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ProductCollection::class);
        $includes = $this->resolveProductCollectionIncludes();

        $collection = app(CreateProductCollection::class)
            ->handle($request->validated());

        return ProductCollectionResource::make($collection->load($this->productCollectionRelationsForIncludes($includes)));
    }

    public function show(ProductCollection $productCollection): JsonResource
    {
        $this->authorizeIfConfigured('view', $productCollection);
        $includes = $this->resolveProductCollectionIncludes();

        return ProductCollectionResource::make($productCollection->load($this->productCollectionRelationsForIncludes($includes)));
    }

    public function update(UpdateProductCollectionRequest $request, ProductCollection $productCollection): JsonResource
    {
        $this->authorizeIfConfigured('update', $productCollection);
        $includes = $this->resolveProductCollectionIncludes();

        $collection = app(UpdateProductCollection::class)
            ->handle($productCollection, $request->validated());

        return ProductCollectionResource::make($collection->load($this->productCollectionRelationsForIncludes($includes)));
    }

    public function destroy(ProductCollection $productCollection)
    {
        $this->authorizeIfConfigured('delete', $productCollection);

        $productCollection->delete();

        return response()->noContent();
    }

    protected function resolveProductCollectionIncludes(): array
    {
        $rawIncludes = request()->query('include', []);

        $includes = collect(is_array($rawIncludes) ? $rawIncludes : [$rawIncludes])
            ->flatMap(fn (mixed $include) => is_string($include) ? explode(',', $include) : [])
            ->map(fn (string $include) => mb_trim($include))
            ->filter(fn (string $include) => filled($include))
            ->unique()
            ->values()
            ->all();

        $this->validateData([
            'include' => $includes,
        ], [
            'include' => ['array'],
            'include.*' => [
                'string',
                Rule::in(['products', 'discounts']),
            ],
        ]);

        return $includes;
    }

    protected function productCollectionRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('products', $includes, true)) {
            $relations[] = 'products';
        }

        if (in_array('discounts', $includes, true)) {
            $relations[] = 'discounts';
        }

        return $relations;
    }
}
