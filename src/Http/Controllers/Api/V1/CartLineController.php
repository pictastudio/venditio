<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\CartLine\{StoreCartLineRequest, UpdateCartLineRequest};
use PictaStudio\Venditio\Http\Resources\V1\CartLineResource;
use PictaStudio\Venditio\Models\CartLine;

use function PictaStudio\Venditio\Helpers\Functions\query;

class CartLineController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', CartLine::class);

        $includes = $this->resolveCartLineIncludes();

        return CartLineResource::collection(
            $this->applyBaseFilters(
                query('cart_line')->with($this->cartLineRelationsForIncludes($includes)),
                request()->except('include'),
                'cart_line'
            )
        );
    }

    public function store(StoreCartLineRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', CartLine::class);

        $payload = $request->validated();
        $payload['currency_id'] = $this->resolveInventoryCurrencyId((int) $payload['product_id']);

        $cartLine = query('cart_line')->create($payload);

        $includes = $this->resolveCartLineIncludes();

        return CartLineResource::make($cartLine->load($this->cartLineRelationsForIncludes($includes)));
    }

    public function show(CartLine $cartLine): JsonResource
    {
        $this->authorizeIfConfigured('view', $cartLine);

        $includes = $this->resolveCartLineIncludes();

        return CartLineResource::make($cartLine->load($this->cartLineRelationsForIncludes($includes)));
    }

    public function update(UpdateCartLineRequest $request, CartLine $cartLine): JsonResource
    {
        $this->authorizeIfConfigured('update', $cartLine);

        $payload = $request->validated();

        if (array_key_exists('product_id', $payload)) {
            $payload['currency_id'] = $this->resolveInventoryCurrencyId((int) $payload['product_id']);
        }

        $cartLine->fill($payload);
        $cartLine->save();

        $includes = $this->resolveCartLineIncludes();

        return CartLineResource::make($cartLine->refresh()->load($this->cartLineRelationsForIncludes($includes)));
    }

    public function destroy(CartLine $cartLine)
    {
        $this->authorizeIfConfigured('delete', $cartLine);

        $cartLine->delete();

        return response()->noContent();
    }

    protected function resolveCartLineIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts());
    }

    protected function cartLineRelationsForIncludes(array $includes): array
    {
        return $this->discountRelationsForIncludes($includes);
    }

    private function resolveInventoryCurrencyId(int $productId): ?int
    {
        $currencyId = query('inventory')
            ->firstWhere('product_id', $productId)
            ?->getAttribute('currency_id');

        $currencyId ??= $this->resolveDefaultCurrencyId();

        if (blank($currencyId)) {
            throw ValidationException::withMessages([
                'currency_id' => ['No default currency configured.'],
            ]);
        }

        return (int) $currencyId;
    }

    private function resolveDefaultCurrencyId(): ?int
    {
        $defaultCurrency = query('currency')
            ->where('is_default', true)
            ->first();

        if ($defaultCurrency) {
            return (int) $defaultCurrency->getKey();
        }

        $fallbackCurrency = query('currency')->first();

        if (!$fallbackCurrency) {
            return null;
        }

        $fallbackCurrency->update(['is_default' => true]);

        return (int) $fallbackCurrency->getKey();
    }
}
