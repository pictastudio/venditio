<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ShippingRateTier\{StoreShippingRateTierRequest, UpdateShippingRateTierRequest};
use PictaStudio\Venditio\Http\Resources\V1\ShippingRateTierResource;
use PictaStudio\Venditio\Models\ShippingRateTier;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ShippingRateTierController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ShippingRateTier::class);

        return ShippingRateTierResource::collection(
            $this->applyBaseFilters(query('shipping_rate_tier'), request()->all(), 'shipping_rate_tier')
        );
    }

    public function store(StoreShippingRateTierRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingRateTier::class);

        $tier = query('shipping_rate_tier')->create($request->validated());

        return ShippingRateTierResource::make($tier);
    }

    public function show(ShippingRateTier $shippingRateTier): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingRateTier);

        return ShippingRateTierResource::make($shippingRateTier);
    }

    public function update(UpdateShippingRateTierRequest $request, ShippingRateTier $shippingRateTier): JsonResource
    {
        $this->authorizeIfConfigured('update', $shippingRateTier);

        $shippingRateTier->fill($request->validated());
        $shippingRateTier->save();

        return ShippingRateTierResource::make($shippingRateTier->refresh());
    }

    public function destroy(ShippingRateTier $shippingRateTier)
    {
        $this->authorizeIfConfigured('delete', $shippingRateTier);

        $shippingRateTier->delete();

        return response()->noContent();
    }
}
