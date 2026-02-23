<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ShippingCarrier\{StoreShippingCarrierRequest, UpdateShippingCarrierRequest};
use PictaStudio\Venditio\Http\Resources\V1\ShippingCarrierResource;
use PictaStudio\Venditio\Models\ShippingCarrier;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ShippingCarrierController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ShippingCarrier::class);

        return ShippingCarrierResource::collection(
            $this->applyBaseFilters(query('shipping_carrier'), request()->all(), 'shipping_carrier')
        );
    }

    public function store(StoreShippingCarrierRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingCarrier::class);

        $carrier = query('shipping_carrier')->create($request->validated());

        return ShippingCarrierResource::make($carrier);
    }

    public function show(ShippingCarrier $shippingCarrier): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingCarrier);

        return ShippingCarrierResource::make($shippingCarrier);
    }

    public function update(UpdateShippingCarrierRequest $request, ShippingCarrier $shippingCarrier): JsonResource
    {
        $this->authorizeIfConfigured('update', $shippingCarrier);

        $shippingCarrier->fill($request->validated());
        $shippingCarrier->save();

        return ShippingCarrierResource::make($shippingCarrier->refresh());
    }

    public function destroy(ShippingCarrier $shippingCarrier)
    {
        $this->authorizeIfConfigured('delete', $shippingCarrier);

        $shippingCarrier->delete();

        return response()->noContent();
    }
}
