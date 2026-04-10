<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Currency, Order, Product, User};

use function Pest\Laravel\{deleteJson, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

function createReturnApiUser(?string $email = null): User
{
    return User::query()->create([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => $email ?? ('user-' . uniqid() . '@example.test'),
        'phone' => '123456789',
    ]);
}

function createReturnReason(array $attributes = []): mixed
{
    $returnReasonModel = config('venditio.models.return_reason');

    return $returnReasonModel::query()->create([
        'code' => 'reason-' . uniqid(),
        'name' => 'Return reason',
        'description' => 'Return reason description',
        'active' => true,
        'sort_order' => 0,
        ...$attributes,
    ]);
}

/**
 * @return array{0: User, 1: Order, 2: mixed}
 */
function createReturnableOrderLine(int $qty = 2, ?User $user = null): array
{
    $user ??= createReturnApiUser();
    $currency = Currency::query()->firstOrFail();
    $product = Product::factory()->create();

    $order = Order::factory()->create([
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'addresses' => [
            'billing' => [
                'country_id' => 1,
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
                'email' => $user->email,
                'address_line_1' => 'Via Roma 1',
                'city' => 'Rome',
            ],
            'shipping' => [
                'country_id' => 1,
            ],
        ],
    ]);

    $orderLineModel = config('venditio.models.order_line');
    $orderLine = $orderLineModel::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $product->getKey(),
        'currency_id' => $currency->getKey(),
        'product_name' => (string) $product->name,
        'product_sku' => (string) $product->sku,
        'unit_price' => 10,
        'unit_final_price' => 10,
        'unit_final_price_tax' => 2,
        'unit_final_price_taxable' => 8,
        'qty' => $qty,
        'total_final_price' => 10 * $qty,
        'tax_rate' => 22,
        'product_data' => [
            'id' => $product->getKey(),
            'name' => (string) $product->name,
            'sku' => (string) $product->sku,
        ],
    ]);

    return [$user, $order, $orderLine];
}

it('supports CRUD and filtering for return reasons', function () {
    $prefix = config('venditio.routes.api.v1.prefix');

    $storeResponse = postJson($prefix . '/return_reasons', [
        'code' => 'size-issue',
        'name' => 'Wrong size',
        'description' => 'The size does not fit.',
    ])->assertCreated()
        ->assertJsonPath('code', 'size-issue')
        ->assertJsonPath('active', true);

    $activeReasonId = (int) $storeResponse->json('id');
    $inactiveReason = createReturnReason([
        'code' => 'inactive-reason',
        'name' => 'Inactive reason',
        'active' => false,
    ]);

    getJson($prefix . '/return_reasons?is_active=1&all=1')
        ->assertOk()
        ->assertJsonFragment(['id' => $activeReasonId])
        ->assertJsonMissing(['id' => $inactiveReason->getKey()]);

    patchJson($prefix . '/return_reasons/' . $activeReasonId, [
        'name' => 'Size mismatch',
        'sort_order' => 5,
    ])->assertOk()
        ->assertJsonPath('name', 'Size mismatch')
        ->assertJsonPath('sort_order', 5);

    deleteJson($prefix . '/return_reasons/' . $activeReasonId)->assertNoContent();

    getJson($prefix . '/return_reasons?only_trashed=1&all=1')
        ->assertOk()
        ->assertJsonFragment(['id' => $activeReasonId]);
});

it('copies the order billing snapshot and rejects order lines from other orders', function () {
    $prefix = config('venditio.routes.api.v1.prefix');
    $returnReason = createReturnReason();
    [, $firstOrder, $firstOrderLine] = createReturnableOrderLine();
    [, , $secondOrderLine] = createReturnableOrderLine();

    postJson($prefix . '/return_requests', [
        'order_id' => $firstOrder->getKey(),
        'return_reason_id' => $returnReason->getKey(),
        'description' => 'Wrong size',
        'lines' => [
            [
                'order_line_id' => $secondOrderLine->getKey(),
                'qty' => 1,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.order_line_id']);

    postJson($prefix . '/return_requests', [
        'order_id' => $firstOrder->getKey(),
        'return_reason_id' => $returnReason->getKey(),
        'description' => 'Wrong size',
        'lines' => [
            [
                'order_line_id' => $firstOrderLine->getKey(),
                'qty' => 1,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('billing_address.first_name', 'Mario')
        ->assertJsonPath('billing_address.address_line_1', 'Via Roma 1');
});

it('tracks partial return quantities and exposes derived flags on order lines and orders', function () {
    $prefix = config('venditio.routes.api.v1.prefix');
    $returnReason = createReturnReason();
    [, $order, $orderLine] = createReturnableOrderLine(qty: 2);

    $returnRequestId = postJson($prefix . '/return_requests', [
        'order_id' => $order->getKey(),
        'return_reason_id' => $returnReason->getKey(),
        'description' => 'Size mismatch',
        'lines' => [
            [
                'order_line_id' => $orderLine->getKey(),
                'qty' => 1,
            ],
        ],
    ])->assertCreated()->json('id');

    getJson($prefix . '/order_lines/' . $orderLine->getKey())
        ->assertOk()
        ->assertJsonPath('requested_return_qty', 1)
        ->assertJsonPath('returned_qty', 0)
        ->assertJsonPath('has_return_requests', true)
        ->assertJsonPath('is_returned', false)
        ->assertJsonPath('is_fully_returned', false);

    patchJson($prefix . '/return_requests/' . $returnRequestId, [
        'is_accepted' => true,
    ])->assertOk()
        ->assertJsonPath('is_accepted', true);

    getJson($prefix . '/order_lines/' . $orderLine->getKey())
        ->assertOk()
        ->assertJsonPath('requested_return_qty', 1)
        ->assertJsonPath('returned_qty', 1)
        ->assertJsonPath('has_return_requests', true)
        ->assertJsonPath('is_returned', true)
        ->assertJsonPath('is_fully_returned', false);

    getJson($prefix . '/orders/' . $order->getKey())
        ->assertOk()
        ->assertJsonPath('lines.0.requested_return_qty', 1)
        ->assertJsonPath('lines.0.returned_qty', 1)
        ->assertJsonPath('lines.0.is_returned', true)
        ->assertJsonPath('lines.0.is_fully_returned', false);
});

it('rejects requests that exceed the remaining available quantity on an order line', function () {
    $prefix = config('venditio.routes.api.v1.prefix');
    $returnReason = createReturnReason();
    [, $order, $orderLine] = createReturnableOrderLine(qty: 2);

    postJson($prefix . '/return_requests', [
        'order_id' => $order->getKey(),
        'return_reason_id' => $returnReason->getKey(),
        'description' => 'First request',
        'lines' => [
            [
                'order_line_id' => $orderLine->getKey(),
                'qty' => 1,
            ],
        ],
    ])->assertCreated();

    postJson($prefix . '/return_requests', [
        'order_id' => $order->getKey(),
        'return_reason_id' => $returnReason->getKey(),
        'description' => 'Second request',
        'lines' => [
            [
                'order_line_id' => $orderLine->getKey(),
                'qty' => 2,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.qty']);
});

it('rejects verified return requests that are not accepted', function () {
    $prefix = config('venditio.routes.api.v1.prefix');
    $returnReason = createReturnReason();
    [, $order, $orderLine] = createReturnableOrderLine();

    postJson($prefix . '/return_requests', [
        'order_id' => $order->getKey(),
        'return_reason_id' => $returnReason->getKey(),
        'description' => 'Wrong color',
        'is_verified' => true,
        'is_accepted' => false,
        'lines' => [
            [
                'order_line_id' => $orderLine->getKey(),
                'qty' => 1,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['is_verified']);
});

it('recalculates derived order line return fields when a return request is deleted', function () {
    $prefix = config('venditio.routes.api.v1.prefix');
    $returnReason = createReturnReason();
    [, $order, $orderLine] = createReturnableOrderLine(qty: 2);

    $returnRequestId = postJson($prefix . '/return_requests', [
        'order_id' => $order->getKey(),
        'return_reason_id' => $returnReason->getKey(),
        'description' => 'Damaged item',
        'is_accepted' => true,
        'lines' => [
            [
                'order_line_id' => $orderLine->getKey(),
                'qty' => 1,
            ],
        ],
    ])->assertCreated()->json('id');

    deleteJson($prefix . '/return_requests/' . $returnRequestId)->assertNoContent();

    getJson($prefix . '/order_lines/' . $orderLine->getKey())
        ->assertOk()
        ->assertJsonPath('requested_return_qty', 0)
        ->assertJsonPath('returned_qty', 0)
        ->assertJsonPath('has_return_requests', false)
        ->assertJsonPath('is_returned', false)
        ->assertJsonPath('is_fully_returned', false);

    getJson($prefix . '/orders/' . $order->getKey())
        ->assertOk()
        ->assertJsonPath('lines.0.requested_return_qty', 0)
        ->assertJsonPath('lines.0.returned_qty', 0)
        ->assertJsonPath('lines.0.is_returned', false);
});
