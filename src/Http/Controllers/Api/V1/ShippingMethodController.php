<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ShippingMethod\{StoreShippingMethodRequest, UpdateShippingMethodRequest};
use PictaStudio\Venditio\Http\Resources\V1\ShippingMethodResource;
use PictaStudio\Venditio\Models\ShippingMethod;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ShippingMethodController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ShippingMethod::class);

        return ShippingMethodResource::collection(
            $this->applyBaseFilters(query('shipping_method'), request()->all(), 'shipping_method')
        );
    }

    public function store(StoreShippingMethodRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingMethod::class);

        $shippingMethod = query('shipping_method')->create($request->validated());

        return ShippingMethodResource::make($shippingMethod->refresh());
    }

    public function show(ShippingMethod $shippingMethod): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingMethod);

        return ShippingMethodResource::make($shippingMethod);
    }

    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod): JsonResource
    {
        $this->authorizeIfConfigured('update', $shippingMethod);

        $shippingMethod->fill($request->validated());
        $shippingMethod->save();

        return ShippingMethodResource::make($shippingMethod->refresh());
    }

    public function destroy(ShippingMethod $shippingMethod)
    {
        $this->authorizeIfConfigured('delete', $shippingMethod);

        $shippingMethod->delete();

        return response()->noContent();
    }
}
