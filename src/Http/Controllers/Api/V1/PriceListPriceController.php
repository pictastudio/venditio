<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\PriceListPrices\UpsertMultiplePriceListPrices;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\PriceListPrice\{StorePriceListPriceRequest, UpdatePriceListPriceRequest, UpsertMultiplePriceListPriceRequest};
use PictaStudio\Venditio\Http\Resources\V1\PriceListPriceResource;
use PictaStudio\Venditio\Models\PriceListPrice;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class PriceListPriceController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->ensureFeatureIsEnabled();
        $this->authorizeIfConfigured('viewAny', resolve_model('price_list_price'));

        $filters = request()->all();

        $this->validateData($filters, [
            'product_id' => ['sometimes', 'integer', Rule::exists((new (resolve_model('product')))->getTable(), 'id')],
            'price_list_id' => ['sometimes', 'integer', Rule::exists((new (resolve_model('price_list')))->getTable(), 'id')],
        ]);

        return PriceListPriceResource::collection(
            $this->applyBaseFilters(
                query('price_list_price')
                    ->when(
                        isset($filters['product_id']),
                        fn ($builder) => $builder->where('product_id', (int) $filters['product_id'])
                    )
                    ->when(
                        isset($filters['price_list_id']),
                        fn ($builder) => $builder->where('price_list_id', (int) $filters['price_list_id'])
                    )
                    ->with('priceList'),
                $filters,
                'price_list_price'
            )
        );
    }

    public function store(StorePriceListPriceRequest $request): JsonResource
    {
        $this->ensureFeatureIsEnabled();
        $this->authorizeIfConfigured('create', resolve_model('price_list_price'));

        $priceListPrice = query('price_list_price')->create($request->validated());
        $this->normalizeDefaultForProduct($priceListPrice);

        return PriceListPriceResource::make($priceListPrice->refresh()->load('priceList'));
    }

    public function show(PriceListPrice $priceListPrice): JsonResource
    {
        $this->ensureFeatureIsEnabled();
        $this->authorizeIfConfigured('view', $priceListPrice);

        return PriceListPriceResource::make($priceListPrice->load('priceList'));
    }

    public function update(UpdatePriceListPriceRequest $request, PriceListPrice $priceListPrice): JsonResource
    {
        $this->ensureFeatureIsEnabled();
        $this->authorizeIfConfigured('update', $priceListPrice);

        $priceListPrice->fill($request->validated());
        $priceListPrice->save();

        $this->normalizeDefaultForProduct($priceListPrice);

        return PriceListPriceResource::make($priceListPrice->refresh()->load('priceList'));
    }

    public function upsertMultiple(UpsertMultiplePriceListPriceRequest $request): JsonResource
    {
        $this->ensureFeatureIsEnabled();

        $validated = $request->validated();
        $prices = collect($validated['prices']);
        $targetTuples = $prices
            ->map(
                fn (array $pricePayload): string => $this->priceTupleKey(
                    (int) $pricePayload['product_id'],
                    (int) $pricePayload['price_list_id']
                )
            )
            ->flip();

        $existingPrices = query('price_list_price')
            ->whereIn(
                'product_id',
                $prices
                    ->pluck('product_id')
                    ->map(fn (mixed $productId): int => (int) $productId)
                    ->unique()
                    ->all()
            )
            ->whereIn(
                'price_list_id',
                $prices
                    ->pluck('price_list_id')
                    ->map(fn (mixed $priceListId): int => (int) $priceListId)
                    ->unique()
                    ->all()
            )
            ->get()
            ->filter(
                fn (PriceListPrice $priceListPrice): bool => $targetTuples->has(
                    $this->priceTupleKey((int) $priceListPrice->product_id, (int) $priceListPrice->price_list_id)
                )
            )
            ->keyBy(
                fn (PriceListPrice $priceListPrice): string => $this->priceTupleKey(
                    (int) $priceListPrice->product_id,
                    (int) $priceListPrice->price_list_id
                )
            );

        $needsCreateAuthorization = false;

        foreach ($prices as $pricePayload) {
            $tupleKey = $this->priceTupleKey((int) $pricePayload['product_id'], (int) $pricePayload['price_list_id']);
            $existingPrice = $existingPrices->get($tupleKey);

            if ($existingPrice instanceof PriceListPrice) {
                $this->authorizeIfConfigured('update', $existingPrice);

                continue;
            }

            $needsCreateAuthorization = true;
        }

        if ($needsCreateAuthorization) {
            $this->authorizeIfConfigured('create', resolve_model('price_list_price'));
        }

        $upsertedPrices = app(UpsertMultiplePriceListPrices::class)
            ->handle($prices->all());

        return PriceListPriceResource::collection($upsertedPrices->load('priceList'));
    }

    public function destroy(PriceListPrice $priceListPrice)
    {
        $this->ensureFeatureIsEnabled();
        $this->authorizeIfConfigured('delete', $priceListPrice);

        $priceListPrice->delete();

        return response()->noContent();
    }

    private function ensureFeatureIsEnabled(): void
    {
        abort_unless(config('venditio.price_lists.enabled', false), 404);
    }

    private function normalizeDefaultForProduct(PriceListPrice $priceListPrice): void
    {
        if (!$priceListPrice->is_default) {
            return;
        }

        query('price_list_price')
            ->where('product_id', $priceListPrice->product_id)
            ->whereKeyNot($priceListPrice->getKey())
            ->update(['is_default' => false]);
    }

    private function priceTupleKey(int $productId, int $priceListId): string
    {
        return $productId . ':' . $priceListId;
    }
}
