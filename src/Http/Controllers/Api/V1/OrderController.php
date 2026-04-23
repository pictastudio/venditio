<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Order\{StoreOrderRequest, UpdateOrderRequest};
use PictaStudio\Venditio\Http\Resources\V1\OrderResource;
use PictaStudio\Venditio\Models\Order;
use PictaStudio\Venditio\Pipelines\Order\OrderCreationPipeline;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_dto};

class OrderController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Order::class);

        $includes = $this->resolveOrderIncludes();
        $filters = request()->except('include');

        // $this->validateData($filters, [
        //     'all' => [
        //         'boolean',
        //     ],
        //     'id' => [
        //         'array',
        //     ],
        //     'id.*' => [
        //         Rule::exists('orders', 'id'),
        //     ],
        // ]);

        return OrderResource::collection(
            $this->applyBaseFilters(
                query('order')->with($this->orderRelationsForIncludes($includes)),
                $filters,
                'order'
            )
        );
    }

    public function store(StoreOrderRequest $request, OrderCreationPipeline $pipeline): JsonResource
    {
        $this->authorizeIfConfigured('create', Order::class);

        $includes = $this->resolveOrderIncludes();
        $order = $pipeline->run(
            resolve_dto('order')::fromCart(
                query('cart')
                    ->where('status', config('venditio.cart.status_enum')::getActiveStatus())
                    ->findOrFail($request->validated('cart_id'))
            )
        );

        return OrderResource::make($order->load($this->orderRelationsForIncludes($includes)));
    }

    public function show(Order $order): JsonResource
    {
        $this->authorizeIfConfigured('view', $order);

        $includes = $this->resolveOrderIncludes();

        return OrderResource::make($order->load($this->orderRelationsForIncludes($includes)));
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResource
    {
        $this->authorizeIfConfigured('update', $order);

        $order->fill($request->validated());
        $order->save();

        $includes = $this->resolveOrderIncludes();

        return OrderResource::make($order->refresh()->load($this->orderRelationsForIncludes($includes)));
    }

    public function destroy(Order $order)
    {
        $this->authorizeIfConfigured('delete', $order);

        $order->delete();

        return response()->noContent();
    }

    protected function resolveOrderIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts());
    }

    protected function orderRelationsForIncludes(array $includes): array
    {
        return [
            'lines',
            'shippingMethod',
            'shippingZone',
            ...$this->discountRelationsForIncludes($includes),
        ];
    }
}
