<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ShippingRate\{StoreShippingRateRequest, UpdateShippingRateRequest};
use PictaStudio\Venditio\Http\Resources\V1\ShippingRateResource;
use PictaStudio\Venditio\Models\ShippingRate;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ShippingRateController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ShippingRate::class);

        return ShippingRateResource::collection(
            $this->applyBaseFilters(
                query('shipping_rate')->with(['shippingCarrier', 'shippingZone', 'tiers']),
                request()->all(),
                'shipping_rate'
            )
        );
    }

    public function store(StoreShippingRateRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingRate::class);

        $rate = query('shipping_rate')->create($request->validated());

        return ShippingRateResource::make($rate->load(['shippingCarrier', 'shippingZone', 'tiers']));
    }

    public function show(ShippingRate $shippingRate): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingRate);

        return ShippingRateResource::make($shippingRate->load(['shippingCarrier', 'shippingZone', 'tiers']));
    }

    public function update(UpdateShippingRateRequest $request, ShippingRate $shippingRate): JsonResource
    {
        $this->authorizeIfConfigured('update', $shippingRate);

        $shippingRate->fill($request->validated());
        $shippingRate->save();

        return ShippingRateResource::make($shippingRate->refresh()->load(['shippingCarrier', 'shippingZone', 'tiers']));
    }

    public function destroy(ShippingRate $shippingRate)
    {
        $this->authorizeIfConfigured('delete', $shippingRate);

        $shippingRate->delete();

        return response()->noContent();
    }
}
