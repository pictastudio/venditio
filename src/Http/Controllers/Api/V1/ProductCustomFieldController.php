<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ProductCustomField\{StoreProductCustomFieldRequest, UpdateProductCustomFieldRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductCustomFieldResource;
use PictaStudio\Venditio\Models\ProductCustomField;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ProductCustomFieldController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ProductCustomField::class);

        return ProductCustomFieldResource::collection(
            $this->applyBaseFilters(query('product_custom_field'), request()->all(), 'product_custom_field')
        );
    }

    public function store(StoreProductCustomFieldRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ProductCustomField::class);

        $productCustomField = query('product_custom_field')->create($request->validated());

        return ProductCustomFieldResource::make($productCustomField);
    }

    public function show(ProductCustomField $productCustomField): JsonResource
    {
        $this->authorizeIfConfigured('view', $productCustomField);

        return ProductCustomFieldResource::make($productCustomField);
    }

    public function update(UpdateProductCustomFieldRequest $request, ProductCustomField $productCustomField): JsonResource
    {
        $this->authorizeIfConfigured('update', $productCustomField);

        $productCustomField->fill($request->validated());
        $productCustomField->save();

        return ProductCustomFieldResource::make($productCustomField->refresh());
    }

    public function destroy(ProductCustomField $productCustomField)
    {
        $this->authorizeIfConfigured('delete', $productCustomField);

        $productCustomField->delete();

        return response()->noContent();
    }
}
