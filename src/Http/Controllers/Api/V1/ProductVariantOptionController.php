<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\ProductVariantOptions\{CreateProductVariantOption, UpdateProductVariantOption};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ProductVariantOption\{StoreProductVariantOptionRequest, UpdateProductVariantOptionRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductVariantOptionResource;
use PictaStudio\Venditio\Models\ProductVariantOption;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class ProductVariantOptionController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ProductVariantOption::class);

        $filters = request()->all();

        $this->validateData($filters, [
            'product_variant_id' => [
                'sometimes',
                'integer',
                Rule::exists((new (resolve_model('product_variant')))->getTable(), 'id'),
            ],
        ]);

        $query = query('product_variant_option')
            ->with('variantProducts')
            ->when(
                isset($filters['product_variant_id']),
                fn ($query) => $query->where('product_variant_id', $filters['product_variant_id'])
            );

        return ProductVariantOptionResource::collection(
            $this->applyBaseFilters($query, $filters, 'product_variant_option')
        );
    }

    public function store(StoreProductVariantOptionRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ProductVariantOption::class);

        $option = app(CreateProductVariantOption::class)
            ->handle($request->validated());

        return ProductVariantOptionResource::make($option->load('variantProducts'));
    }

    public function show(ProductVariantOption $productVariantOption): JsonResource
    {
        $this->authorizeIfConfigured('view', $productVariantOption);

        return ProductVariantOptionResource::make($productVariantOption->load('variantProducts'));
    }

    public function update(UpdateProductVariantOptionRequest $request, ProductVariantOption $productVariantOption): JsonResource
    {
        $this->authorizeIfConfigured('update', $productVariantOption);

        $productVariantOption = app(UpdateProductVariantOption::class)
            ->handle($productVariantOption, $request->validated());

        return ProductVariantOptionResource::make($productVariantOption->load('variantProducts'));
    }

    public function destroy(ProductVariantOption $productVariantOption)
    {
        $this->authorizeIfConfigured('delete', $productVariantOption);

        $productVariantOption->delete();

        return response()->noContent();
    }
}
