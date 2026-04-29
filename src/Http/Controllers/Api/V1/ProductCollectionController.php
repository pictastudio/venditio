<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\CatalogImages\DeleteCatalogImage;
use PictaStudio\Venditio\Actions\ProductCollections\{CreateProductCollection, UpdateProductCollection};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ProductCollection\{StoreProductCollectionRequest, UpdateProductCollectionRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductCollectionResource;
use PictaStudio\Venditio\Models\ProductCollection;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class ProductCollectionController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ProductCollection::class);

        $includes = $this->resolveProductCollectionIncludes();
        $filters = request()->except('include');
        $query = query('product_collection')->with($this->productCollectionRelationsForIncludes($includes));
        $this->applyProductCollectionIndexRelationFilters($query, $filters);
        $this->loadProductCollectionProductCountIfRequested($query, $includes);

        return ProductCollectionResource::collection(
            $this->applyBaseFilters(
                $query,
                $filters,
                'product_collection',
                $this->productCollectionIndexValidationRules()
            )
        );
    }

    public function store(StoreProductCollectionRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ProductCollection::class);
        $includes = $this->resolveProductCollectionIncludes();

        $collection = app(CreateProductCollection::class)
            ->handle($request->validated());

        $collection->load($this->productCollectionRelationsForIncludes($includes));
        $this->loadProductCollectionProductCountIfRequested($collection, $includes);

        return ProductCollectionResource::make($collection);
    }

    public function show(ProductCollection $productCollection): JsonResource
    {
        $this->authorizeIfConfigured('view', $productCollection);
        $includes = $this->resolveProductCollectionIncludes();

        $productCollection->load($this->productCollectionRelationsForIncludes($includes));
        $this->loadProductCollectionProductCountIfRequested($productCollection, $includes);

        return ProductCollectionResource::make($productCollection);
    }

    public function update(UpdateProductCollectionRequest $request, ProductCollection $productCollection): JsonResource
    {
        $this->authorizeIfConfigured('update', $productCollection);
        $includes = $this->resolveProductCollectionIncludes();

        $collection = app(UpdateProductCollection::class)
            ->handle($productCollection, $request->validated());

        $collection->load($this->productCollectionRelationsForIncludes($includes));
        $this->loadProductCollectionProductCountIfRequested($collection, $includes);

        return ProductCollectionResource::make($collection);
    }

    public function destroy(ProductCollection $productCollection)
    {
        $this->authorizeIfConfigured('delete', $productCollection);

        $productCollection->delete();

        return response()->noContent();
    }

    public function destroyImage(ProductCollection $productCollection, string $imageId, DeleteCatalogImage $action)
    {
        $this->authorizeIfConfigured('update', $productCollection);

        $action->handle($productCollection, $imageId);

        return response()->noContent();
    }

    protected function resolveProductCollectionIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts(['products', 'products_count', 'tags']));
    }

    protected function productCollectionRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('products', $includes, true)) {
            $relations[] = 'products';
        }

        if (in_array('tags', $includes, true)) {
            $relations[] = 'tags';
        }

        return [
            ...$relations,
            ...$this->discountRelationsForIncludes($includes),
        ];
    }

    protected function productCollectionIndexValidationRules(): array
    {
        $tagModel = app(resolve_model('tag'));
        $tagTable = method_exists($tagModel, 'getTableName')
            ? $tagModel->getTableName()
            : $tagModel->getTable();

        return [
            'tag_ids' => ['sometimes', 'array', 'min:1'],
            'tag_ids.*' => [
                'integer',
                Rule::exists($tagTable, $tagModel->getKeyName()),
            ],
        ];
    }

    protected function applyProductCollectionIndexRelationFilters(Builder $query, array &$filters): void
    {
        if (isset($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            $query->whereHas(
                'tags',
                fn (Builder $tagsQuery) => $tagsQuery->whereKey($filters['tag_ids'])
            );
        }
    }

    protected function loadProductCollectionProductCountIfRequested(Builder|ProductCollection $target, array $includes): void
    {
        if (!in_array('products_count', $includes, true)) {
            return;
        }

        if ($target instanceof Builder) {
            $target->withCount('products');

            return;
        }

        $target->loadCount('products');
    }
}
