<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ShippingZone\{StoreShippingZoneRequest, UpdateShippingZoneRequest};
use PictaStudio\Venditio\Http\Resources\V1\ShippingZoneResource;
use PictaStudio\Venditio\Models\ShippingZone;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ShippingZoneController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ShippingZone::class);

        return ShippingZoneResource::collection(
            $this->applyBaseFilters(query('shipping_zone'), request()->all(), 'shipping_zone')
        );
    }

    public function store(StoreShippingZoneRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingZone::class);

        $zone = query('shipping_zone')->create($request->validated());

        return ShippingZoneResource::make($zone);
    }

    public function show(ShippingZone $shippingZone): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingZone);

        return ShippingZoneResource::make($shippingZone);
    }

    public function update(UpdateShippingZoneRequest $request, ShippingZone $shippingZone): JsonResource
    {
        $this->authorizeIfConfigured('update', $shippingZone);

        $shippingZone->fill($request->validated());
        $shippingZone->save();

        return ShippingZoneResource::make($shippingZone->refresh());
    }

    public function destroy(ShippingZone $shippingZone)
    {
        $this->authorizeIfConfigured('delete', $shippingZone);

        $shippingZone->delete();

        return response()->noContent();
    }
}
