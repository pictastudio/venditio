<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Enums\CartFreeGiftDecisionType;
use PictaStudio\Venditio\FreeGifts\FreeGiftEligibilityResolver;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Cart\{StoreCartRequest, UpdateCartFreeGiftsRequest, UpdateCartRequest};
use PictaStudio\Venditio\Http\Resources\V1\CartResource;
use PictaStudio\Venditio\Models\Cart;
use PictaStudio\Venditio\Pipelines\Cart\{CartCreationPipeline, CartUpdatePipeline};
use PictaStudio\Venditio\Validations\Contracts\CartLineValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_dto};

class CartController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Cart::class);

        $includes = $this->resolveCartIncludes();
        $filters = request()->except('include');

        $this->validateData($filters, [
            'user_id' => [
                'sometimes',
                'integer',
                Rule::exists((new (config('venditio.models.user')))->getTable(), 'id'),
            ],
        ]);

        return CartResource::collection(
            $this->applyBaseFilters(
                query('cart')
                    ->with($this->cartRelationsForIncludes($includes))
                    ->when(
                        isset($filters['user_id']),
                        fn (Builder $builder) => $builder->where('user_id', $filters['user_id']),
                    ),
                $filters,
                'cart'
            )
        );
    }

    public function store(StoreCartRequest $request, CartCreationPipeline $pipeline): JsonResource
    {
        $this->authorizeIfConfigured('create', Cart::class);

        $includes = $this->resolveCartIncludes();

        return CartResource::make(
            $pipeline->run(
                resolve_dto('cart')::fromArray($request->validated())
            )->load($this->cartRelationsForIncludes($includes))
        );
    }

    public function show(Cart $cart): JsonResource
    {
        $this->authorizeIfConfigured('view', $cart);

        $includes = $this->resolveCartIncludes();

        return CartResource::make($cart->load($this->cartRelationsForIncludes($includes)));
    }

    public function update(UpdateCartRequest $request, Cart $cart, CartUpdatePipeline $pipeline): JsonResource
    {
        $this->authorizeIfConfigured('update', $cart);

        $includes = $this->resolveCartIncludes();

        return CartResource::make(
            $pipeline->run(
                resolve_dto('cart')::fromArray(
                    array_merge(
                        $request->validated(),
                        ['cart' => $cart]
                    )
                )
            )->load($this->cartRelationsForIncludes($includes))
        );
    }

    public function destroy(Cart $cart): JsonResponse
    {
        $this->authorizeIfConfigured('delete', $cart);

        $cart->purge();

        return $this->successJsonResponse(
            message: 'Cart deleted successfully',
        );
    }

    public function addLines(Cart $cart, CartLineValidationRules $cartLineValidationRules): JsonResponse
    {
        $this->authorizeIfConfigured('update', $cart);

        $validationResponse = $this->validateData(request()->all(), $cartLineValidationRules->getStoreValidationRules());
        $lines = $this->mergeExistingAndIncomingLines($cart, $validationResponse['lines']);
        $updatedCart = $this->runCartUpdatePipeline($cart, ['lines' => $lines])->load(['lines', 'shippingMethod', 'shippingZone']);

        return response()->json(CartResource::make($updatedCart));
    }

    public function updateLines(Cart $cart, CartLineValidationRules $cartLineValidationRules): JsonResponse
    {
        $this->authorizeIfConfigured('update', $cart);

        $validationResponse = $this->validateData(request()->all(), $cartLineValidationRules->getUpdateValidationRules());
        $lineIds = collect($validationResponse['lines'])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $cartLineIds = $cart->lines()
            ->where('is_free_gift', false)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);
        $lineIdsNotBelongingToCart = collect($lineIds)->diff($cartLineIds);

        if ($lineIdsNotBelongingToCart->isNotEmpty()) {
            return $this->errorJsonResponse(
                data: ['line_ids' => $lineIdsNotBelongingToCart->values()->all()],
                message: 'Some lines do not belong to the provided cart.',
                status: 422,
            );
        }

        // pipeline per update cart lines

        foreach ($validationResponse['lines'] as $line) {
            $cart->lines()->find($line['id'])->update([
                'qty' => $line['qty'],
            ]);
        }

        $updatedCart = $this->runCartUpdatePipeline(
            $cart,
            [
                'lines' => $cart->lines()
                    ->where('is_free_gift', false)
                    ->get(['product_id', 'qty'])
                    ->map(fn ($line) => [
                        'product_id' => $line->product_id,
                        'qty' => $line->qty,
                    ])
                    ->toArray(),
            ]
        )->load(['lines', 'shippingMethod', 'shippingZone']);

        return response()->json(CartResource::make($updatedCart));
    }

    public function removeLines(Cart $cart): JsonResponse
    {
        $this->authorizeIfConfigured('update', $cart);

        $validated = $this->validateData(request()->all(), [
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => [
                'integer',
                Rule::exists((new (config('venditio.models.cart_line')))->getTable(), 'id'),
            ],
        ]);

        $lineIds = collect($validated['line_ids'])->map(fn ($id) => (int) $id)->all();
        $cartLines = $cart->lines()
            ->where('is_free_gift', false)
            ->get(['id', 'product_id', 'qty']);
        $lineIdsNotBelongingToCart = collect($lineIds)->diff($cartLines->pluck('id'));

        if ($lineIdsNotBelongingToCart->isNotEmpty()) {
            return $this->errorJsonResponse(
                data: ['line_ids' => $lineIdsNotBelongingToCart->values()->all()],
                message: 'Some lines do not belong to the provided cart.',
                status: 422,
            );
        }

        $remainingLines = $cartLines
            ->reject(fn ($line) => in_array($line->id, $lineIds, true))
            ->groupBy('product_id')
            ->map(fn (Collection $lines) => [
                'product_id' => (int) $lines->first()->product_id,
                'qty' => (int) $lines->sum('qty'),
            ])
            ->values()
            ->all();

        $updatedCart = $this->runCartUpdatePipeline($cart, ['lines' => $remainingLines])->load(['lines', 'shippingMethod', 'shippingZone']);

        return response()->json(CartResource::make($updatedCart));
    }

    public function addDiscount(Cart $cart): JsonResponse
    {
        $this->authorizeIfConfigured('update', $cart);

        $validated = $this->validateData(request()->all(), [
            'discount_code' => ['required', 'string', 'max:255'],
        ]);

        $updatedCart = $this->runCartUpdatePipeline(
            $cart,
            [
                'discount_code' => $validated['discount_code'],
            ]
        )->load(['lines', 'shippingMethod', 'shippingZone']);

        return response()->json(CartResource::make($updatedCart));
    }

    public function updateFreeGifts(UpdateCartFreeGiftsRequest $request, Cart $cart): JsonResponse
    {
        $this->authorizeIfConfigured('update', $cart);

        $payloads = collect($request->validated()['free_gifts']);
        $freeGiftModel = config('venditio.models.free_gift');
        $eligibleFreeGifts = app(FreeGiftEligibilityResolver::class)
            ->resolveForCart($cart)
            ->keyBy(fn (mixed $freeGift): int => (int) $freeGift->getKey());
        $freeGifts = $freeGiftModel::query()
            ->with('giftProducts')
            ->whereKey(
                $payloads->pluck('free_gift_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all()
            )
            ->get()
            ->keyBy(fn (mixed $freeGift): int => (int) $freeGift->getKey());
        $decisionsModel = config('venditio.models.cart_free_gift_decision');

        foreach ($payloads as $payload) {
            $freeGiftId = (int) $payload['free_gift_id'];
            $freeGift = $freeGifts->get($freeGiftId);

            if ($freeGift === null || !$eligibleFreeGifts->has($freeGiftId)) {
                return $this->errorJsonResponse(
                    data: ['free_gift_id' => $freeGiftId],
                    message: "The free gift [{$freeGiftId}] is invalid or not eligible for the provided cart.",
                    status: 422,
                );
            }

            $giftProductIds = $freeGift->giftProducts
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $selectedProductIds = collect($payload['selected_product_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $declinedProductIds = collect($payload['declined_product_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $unknownProductIds = collect($selectedProductIds)
                ->merge($declinedProductIds)
                ->unique()
                ->reject(fn (int $productId): bool => in_array($productId, $giftProductIds, true))
                ->values()
                ->all();

            if ($unknownProductIds !== []) {
                return $this->errorJsonResponse(
                    data: ['product_ids' => $unknownProductIds],
                    message: 'Some selected products do not belong to the provided free gift campaign.',
                    status: 422,
                );
            }

            $selectionMode = is_object($freeGift->selection_mode) && isset($freeGift->selection_mode->value)
                ? $freeGift->selection_mode->value
                : $freeGift->selection_mode;

            if ($selectionMode === 'single' && count($selectedProductIds) > 1) {
                return $this->errorJsonResponse(
                    data: ['free_gift_id' => $freeGiftId],
                    message: 'Single-choice free gift campaigns accept at most one selected product.',
                    status: 422,
                );
            }

            if (!(bool) $freeGift->allow_decline && $declinedProductIds !== []) {
                return $this->errorJsonResponse(
                    data: ['free_gift_id' => $freeGiftId],
                    message: 'This free gift campaign does not allow declining gift products.',
                    status: 422,
                );
            }

            $decisionsModel::query()
                ->where('cart_id', $cart->getKey())
                ->where('free_gift_id', $freeGiftId)
                ->delete();

            foreach ($selectedProductIds as $productId) {
                $decisionsModel::query()->create([
                    'cart_id' => $cart->getKey(),
                    'free_gift_id' => $freeGiftId,
                    'product_id' => $productId,
                    'decision' => CartFreeGiftDecisionType::Selected->value,
                ]);
            }

            foreach ($declinedProductIds as $productId) {
                $decisionsModel::query()->create([
                    'cart_id' => $cart->getKey(),
                    'free_gift_id' => $freeGiftId,
                    'product_id' => $productId,
                    'decision' => CartFreeGiftDecisionType::Declined->value,
                ]);
            }
        }

        $updatedCart = $this->runCartUpdatePipeline($cart, [])
            ->load(['lines', 'shippingMethod', 'shippingZone']);

        return response()->json(CartResource::make($updatedCart));
    }

    private function mergeExistingAndIncomingLines(Cart $cart, array $incomingLines): array
    {
        $existingLines = $cart->lines()
            ->where('is_free_gift', false)
            ->get(['product_id', 'qty'])
            ->groupBy('product_id')
            ->map(fn (Collection $lines, mixed $productId) => [
                'product_id' => (int) $productId,
                'qty' => (int) $lines->sum('qty'),
            ])
            ->values();

        $incomingGrouped = collect($incomingLines)
            ->groupBy('product_id')
            ->map(fn (Collection $lines, mixed $productId) => [
                'product_id' => (int) $productId,
                'qty' => (int) $lines->sum('qty'),
            ])
            ->values();

        return $existingLines
            ->concat($incomingGrouped)
            ->groupBy('product_id')
            ->map(fn (Collection $lines) => [
                'product_id' => (int) $lines->first()['product_id'],
                'qty' => (int) $lines->sum('qty'),
            ])
            ->values()
            ->all();
    }

    private function runCartUpdatePipeline(Cart $cart, array $payload): Cart
    {
        /** @var CartUpdatePipeline $pipeline */
        $pipeline = app(CartUpdatePipeline::class);

        $payload = array_merge(
            [
                'cart' => $cart,
            ],
            $payload
        );

        return $pipeline->run(
            resolve_dto('cart')::fromArray($payload)
        );
    }

    protected function resolveCartIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts());
    }

    protected function cartRelationsForIncludes(array $includes): array
    {
        return [
            'lines',
            'shippingMethod',
            'shippingZone',
            ...$this->discountRelationsForIncludes($includes),
        ];
    }
}
