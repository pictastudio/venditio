<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Actions\ProductTypes\{CreateProductType, UpdateProductType};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ProductType\{StoreProductTypeRequest, UpdateProductTypeRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductTypeResource;
use PictaStudio\Venditio\Models\ProductType;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ProductTypeController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ProductType::class);

        $includes = $this->resolveProductTypeIncludes();
        $filters = request()->except('include');

        return ProductTypeResource::collection(
            $this->applyBaseFilters(
                query('product_type')->with($this->productTypeRelationsForIncludes($includes)),
                $filters,
                'product_type'
            )
        );
    }

    public function store(StoreProductTypeRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ProductType::class);
        $includes = $this->resolveProductTypeIncludes();

        $productType = app(CreateProductType::class)
            ->handle($request->validated());

        return ProductTypeResource::make($productType->load($this->productTypeRelationsForIncludes($includes)));
    }

    public function show(ProductType $productType): JsonResource
    {
        $this->authorizeIfConfigured('view', $productType);
        $includes = $this->resolveProductTypeIncludes();

        return ProductTypeResource::make($productType->load($this->productTypeRelationsForIncludes($includes)));
    }

    public function update(UpdateProductTypeRequest $request, ProductType $productType): JsonResource
    {
        $this->authorizeIfConfigured('update', $productType);
        $includes = $this->resolveProductTypeIncludes();

        $productType = app(UpdateProductType::class)
            ->handle($productType, $request->validated());

        return ProductTypeResource::make($productType->load($this->productTypeRelationsForIncludes($includes)));
    }

    public function destroy(ProductType $productType)
    {
        $this->authorizeIfConfigured('delete', $productType);

        $productType->delete();

        return response()->noContent();
    }

    protected function resolveProductTypeIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts());
    }

    protected function productTypeRelationsForIncludes(array $includes): array
    {
        return $this->discountRelationsForIncludes($includes);
    }
}
