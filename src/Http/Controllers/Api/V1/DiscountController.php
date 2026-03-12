<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Actions\Discounts\UpsertMultipleDiscounts;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Discount\{StoreDiscountRequest, UpdateDiscountRequest, UpsertMultipleDiscountRequest};
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

    public function upsertMultiple(UpsertMultipleDiscountRequest $request): JsonResource
    {
        $validated = $request->validated();
        $discounts = collect($validated['discounts']);
        $discountIds = $discounts
            ->pluck('id')
            ->filter(fn (mixed $discountId): bool => filled($discountId))
            ->map(fn (mixed $discountId): int => (int) $discountId)
            ->unique()
            ->values()
            ->all();

        $existingDiscounts = query('discount')
            ->withTrashed()
            ->whereKey($discountIds)
            ->get()
            ->keyBy(fn (Discount $discount): int => (int) $discount->getKey());

        if ($existingDiscounts->count() !== count($discountIds)) {
            $missingIds = collect($discountIds)
                ->diff($existingDiscounts->keys())
                ->values()
                ->all();

            throw ValidationException::withMessages([
                'discounts' => [
                    'Some discounts are not available for update: ' . implode(', ', $missingIds),
                ],
            ]);
        }

        $needsCreateAuthorization = false;

        foreach ($discounts as $discountPayload) {
            $discountId = $discountPayload['id'] ?? null;

            if (filled($discountId)) {
                $this->authorizeIfConfigured('update', $existingDiscounts->get((int) $discountId));

                continue;
            }

            $needsCreateAuthorization = true;
        }

        if ($needsCreateAuthorization) {
            $this->authorizeIfConfigured('create', Discount::class);
        }

        $upsertedDiscounts = app(UpsertMultipleDiscounts::class)
            ->handle($discounts->all());

        return DiscountResource::collection($upsertedDiscounts);
    }

    public function destroy(Discount $discount)
    {
        $this->authorizeIfConfigured('delete', $discount);

        $discount->delete();

        return response()->noContent();
    }
}
