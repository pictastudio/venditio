<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ShippingZoneMember\StoreShippingZoneMemberRequest;
use PictaStudio\Venditio\Http\Resources\V1\ShippingZoneMemberResource;
use PictaStudio\Venditio\Models\ShippingZoneMember;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ShippingZoneMemberController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ShippingZoneMember::class);

        return ShippingZoneMemberResource::collection(
            $this->applyBaseFilters(
                query('shipping_zone_member')->with(['shippingZone', 'zoneable']),
                request()->all(),
                'shipping_zone_member'
            )
        );
    }

    public function store(StoreShippingZoneMemberRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ShippingZoneMember::class);

        $member = query('shipping_zone_member')->create($request->validated());

        return ShippingZoneMemberResource::make($member->load(['shippingZone', 'zoneable']));
    }

    public function show(ShippingZoneMember $shippingZoneMember): JsonResource
    {
        $this->authorizeIfConfigured('view', $shippingZoneMember);

        return ShippingZoneMemberResource::make($shippingZoneMember->load(['shippingZone', 'zoneable']));
    }

    public function destroy(ShippingZoneMember $shippingZoneMember)
    {
        $this->authorizeIfConfigured('delete', $shippingZoneMember);

        $shippingZoneMember->delete();

        return response()->noContent();
    }
}
