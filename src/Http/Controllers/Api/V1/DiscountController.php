<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\{Builder, Model as EloquentModel};
use Illuminate\Database\Eloquent\Relations\Relation;
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

        $filters = request()->all();
        $query = query('discount');

        $this->applyDiscountIndexFilters($query, $filters);
        unset($filters['is_general'], $filters['discountable_type']);

        return DiscountResource::collection(
            $this->applyBaseFilters($query, $filters, 'discount')
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

    protected function applyDiscountIndexFilters(Builder $query, array $filters): void
    {
        $this->validateData($filters, [
            'is_general' => ['sometimes', 'boolean'],
            'discountable_type' => ['sometimes', 'string', 'max:255'],
        ]);

        if (array_key_exists('is_general', $filters)) {
            $isGeneral = filter_var($filters['is_general'], FILTER_VALIDATE_BOOL);

            if ($isGeneral) {
                $query->whereNull('discountable_type')
                    ->whereNull('discountable_id');
            } else {
                $query->whereNotNull('discountable_type')
                    ->whereNotNull('discountable_id');
            }
        }

        if (filled($filters['discountable_type'] ?? null)) {
            $query->where(
                'discountable_type',
                $this->normalizeDiscountableTypeFilter((string) $filters['discountable_type'])
            );
        }
    }

    protected function normalizeDiscountableTypeFilter(string $discountableType): string
    {
        $resolvedMorphedModel = Relation::getMorphedModel($discountableType);

        if (is_string($resolvedMorphedModel) && is_a($resolvedMorphedModel, EloquentModel::class, true)) {
            return app($resolvedMorphedModel)->getMorphClass();
        }

        if (is_a($discountableType, EloquentModel::class, true)) {
            return app($discountableType)->getMorphClass();
        }

        return $discountableType;
    }
}
