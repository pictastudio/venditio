<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\OrderLine\{StoreOrderLineRequest, UpdateOrderLineRequest};
use PictaStudio\Venditio\Http\Resources\V1\OrderLineResource;
use PictaStudio\Venditio\Models\OrderLine;

use function PictaStudio\Venditio\Helpers\Functions\query;

class OrderLineController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', OrderLine::class);

        return OrderLineResource::collection(
            $this->applyBaseFilters(query('order_line'), request()->all(), 'order_line')
        );
    }

    public function store(StoreOrderLineRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', OrderLine::class);

        $payload = $request->validated();
        $payload['currency_id'] = $this->resolveInventoryCurrencyId((int) $payload['product_id']);

        $orderLine = query('order_line')->create($payload);

        return OrderLineResource::make($orderLine);
    }

    public function show(OrderLine $orderLine): JsonResource
    {
        $this->authorizeIfConfigured('view', $orderLine);

        return OrderLineResource::make($orderLine);
    }

    public function update(UpdateOrderLineRequest $request, OrderLine $orderLine): JsonResource
    {
        $this->authorizeIfConfigured('update', $orderLine);

        $payload = $request->validated();

        if (array_key_exists('product_id', $payload)) {
            $payload['currency_id'] = $this->resolveInventoryCurrencyId((int) $payload['product_id']);
        }

        $orderLine->fill($payload);
        $orderLine->save();

        return OrderLineResource::make($orderLine->refresh());
    }

    public function destroy(OrderLine $orderLine)
    {
        $this->authorizeIfConfigured('delete', $orderLine);

        $orderLine->delete();

        return response()->noContent();
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
