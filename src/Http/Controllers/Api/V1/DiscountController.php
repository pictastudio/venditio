<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Discount\{StoreDiscountRequest, UpdateDiscountRequest};
use PictaStudio\Venditio\Http\Resources\V1\DiscountResource;
use PictaStudio\Venditio\Models\Discount;

use function PictaStudio\Venditio\Helpers\Functions\query;

class DiscountController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Discount::class);

        return DiscountResource::collection(
            $this->applyBaseFilters(query('discount'), request()->all(), 'discount')
        );
    }

    public function store(StoreDiscountRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', Discount::class);

        $discount = query('discount')->create($request->validated());

        return DiscountResource::make($discount);
    }

    public function show(Discount $discount): JsonResource
    {
        $this->authorizeIfConfigured('view', $discount);

        return DiscountResource::make($discount->load('discountable'));
    }

    public function update(UpdateDiscountRequest $request, Discount $discount): JsonResource
    {
        $this->authorizeIfConfigured('update', $discount);

        $discount->fill($request->validated());
        $discount->save();

        return DiscountResource::make($discount->refresh());
    }

    public function destroy(Discount $discount)
    {
        $this->authorizeIfConfigured('delete', $discount);

        $discount->delete();

        return response()->noContent();
    }
}
