<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
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
            'include.*' => ['string', Rule::in(['discounts'])],
        ]);

        return $includes;
    }

    protected function productTypeRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('discounts', $includes, true)) {
            $relations[] = 'discounts';
        }

        return $relations;
    }
}
