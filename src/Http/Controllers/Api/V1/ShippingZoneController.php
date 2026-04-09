<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Actions\ShippingZones\{CreateShippingZone, UpdateShippingZone};
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
            $this->applyBaseFilters(
                query('shipping_zone')->with(['countries', 'regions', 'provinces']),
                request()->all(),
                'shipping_zone'
            )
        );
    }

    public function store(StoreShippingZoneRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingZone::class);

        $shippingZone = app(CreateShippingZone::class)->handle($request->validated());

        return ShippingZoneResource::make(
            $shippingZone->load(['countries', 'regions', 'provinces'])
        );
    }

    public function show(ShippingZone $shippingZone): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingZone);

        return ShippingZoneResource::make(
            $shippingZone->load(['countries', 'regions', 'provinces'])
        );
    }

    public function update(UpdateShippingZoneRequest $request, ShippingZone $shippingZone): JsonResource
    {
        $this->authorizeIfConfigured('update', $shippingZone);

        $shippingZone = app(UpdateShippingZone::class)->handle($shippingZone, $request->validated());

        return ShippingZoneResource::make(
            $shippingZone->load(['countries', 'regions', 'provinces'])
        );
    }

    public function destroy(ShippingZone $shippingZone)
    {
        $this->authorizeIfConfigured('delete', $shippingZone);

        $shippingZone->delete();

        return response()->noContent();
    }
}
