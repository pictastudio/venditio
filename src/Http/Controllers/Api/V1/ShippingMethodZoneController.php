<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ShippingMethodZone\{StoreShippingMethodZoneRequest, UpdateShippingMethodZoneRequest};
use PictaStudio\Venditio\Http\Resources\V1\ShippingMethodZoneResource;
use PictaStudio\Venditio\Models\ShippingMethodZone;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ShippingMethodZoneController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ShippingMethodZone::class);

        return ShippingMethodZoneResource::collection(
            $this->applyBaseFilters(
                query('shipping_method_zone')->with(['shippingMethod', 'shippingZone']),
                request()->all(),
                'shipping_method_zone'
            )
        );
    }

    public function store(StoreShippingMethodZoneRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingMethodZone::class);

        $shippingMethodZone = query('shipping_method_zone')->create($request->validated());

        return ShippingMethodZoneResource::make(
            $shippingMethodZone->refresh()->load(['shippingMethod', 'shippingZone'])
        );
    }

    public function show(ShippingMethodZone $shippingMethodZone): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingMethodZone);

        return ShippingMethodZoneResource::make(
            $shippingMethodZone->load(['shippingMethod', 'shippingZone'])
        );
    }

    public function update(UpdateShippingMethodZoneRequest $request, ShippingMethodZone $shippingMethodZone): JsonResource
    {
        $this->authorizeIfConfigured('update', $shippingMethodZone);

        $shippingMethodZone->fill($request->validated());
        $shippingMethodZone->save();

        return ShippingMethodZoneResource::make(
            $shippingMethodZone->refresh()->load(['shippingMethod', 'shippingZone'])
        );
    }

    public function destroy(ShippingMethodZone $shippingMethodZone)
    {
        $this->authorizeIfConfigured('delete', $shippingMethodZone);

        $shippingMethodZone->delete();

        return response()->noContent();
    }
}
