<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ShippingStatus\{StoreShippingStatusRequest, UpdateShippingStatusRequest};
use PictaStudio\Venditio\Http\Resources\V1\ShippingStatusResource;
use PictaStudio\Venditio\Models\ShippingStatus;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ShippingStatusController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ShippingStatus::class);

        return ShippingStatusResource::collection(
            $this->applyBaseFilters(query('shipping_status'), request()->all(), 'shipping_status')
        );
    }

    public function store(StoreShippingStatusRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingStatus::class);

        $shippingStatus = query('shipping_status')->create($request->validated());

        return ShippingStatusResource::make($shippingStatus);
    }

    public function show(ShippingStatus $shippingStatus): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingStatus);

        return ShippingStatusResource::make($shippingStatus);
    }

    public function update(UpdateShippingStatusRequest $request, ShippingStatus $shippingStatus): JsonResource
    {
        $this->authorizeIfConfigured('update', $shippingStatus);

        $shippingStatus->fill($request->validated());
        $shippingStatus->save();

        return ShippingStatusResource::make($shippingStatus->refresh());
    }

    public function destroy(ShippingStatus $shippingStatus)
    {
        $this->authorizeIfConfigured('delete', $shippingStatus);

        $shippingStatus->delete();

        return response()->noContent();
    }
}
