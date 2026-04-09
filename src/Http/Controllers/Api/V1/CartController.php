<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Cart\{StoreCartRequest, UpdateCartRequest};
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

        $filters = request()->all();

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
                    ->with('lines')
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

        return CartResource::make(
            $pipeline->run(
                resolve_dto('cart')::fromArray($request->validated())
            )
        );
    }

    public function show(Cart $cart): JsonResource
    {
        $this->authorizeIfConfigured('view', $cart);

        return CartResource::make($cart->load('lines'));
    }

    public function update(UpdateCartRequest $request, Cart $cart, CartUpdatePipeline $pipeline): JsonResource
    {
        $this->authorizeIfConfigured('update', $cart);

        return CartResource::make(
            $pipeline->run(
                resolve_dto('cart')::fromArray(
                    array_merge(
                        $request->validated(),
                        ['cart' => $cart]
                    )
                )
            )
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
        $updatedCart = $this->runCartUpdatePipeline($cart, ['lines' => $lines])->load('lines');

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
                    ->get(['product_id', 'qty'])
                    ->map(fn ($line) => [
                        'product_id' => $line->product_id,
                        'qty' => $line->qty,
                    ])
                    ->toArray(),
            ]
        )->load('lines');

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
        $cartLines = $cart->lines()->get(['id', 'product_id', 'qty']);
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

        $updatedCart = $this->runCartUpdatePipeline($cart, ['lines' => $remainingLines])->load('lines');

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
        )->load('lines');

        return response()->json(CartResource::make($updatedCart));
    }

    private function mergeExistingAndIncomingLines(Cart $cart, array $incomingLines): array
    {
        $existingLines = $cart->lines()
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
}
