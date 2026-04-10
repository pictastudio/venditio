<?php

namespace PictaStudio\Venditio\Actions\Returns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Arr, Fluent};
use Illuminate\Support\Facades\DB;

use function PictaStudio\Venditio\Helpers\Functions\query;

class CreateReturnRequest
{
    public function __construct(
        private readonly SyncReturnRequestLines $syncReturnRequestLines,
        private readonly RecalculateOrderLineReturnState $recalculateOrderLineReturnState,
    ) {}

    public function handle(array $payload): Model
    {
        return DB::transaction(function () use ($payload): Model {
            $lines = Arr::pull($payload, 'lines', []);

            /** @var Model $order */
            $order = query('order')->findOrFail((int) $payload['order_id']);

            $returnRequest = query('return_request')->create([
                ...$payload,
                'user_id' => $order->user_id,
                'billing_address' => $this->extractBillingAddress($order),
            ]);

            $touchedOrderLineIds = $this->syncReturnRequestLines->handle($returnRequest, $lines);
            $this->recalculateOrderLineReturnState->handle($touchedOrderLineIds);

            return $returnRequest->refresh()->load($this->relations());
        });
    }

    private function extractBillingAddress(Model $order): array
    {
        $addresses = $order->addresses;

        if ($addresses instanceof Fluent) {
            $addresses = $addresses->toArray();
        }

        if (!is_array($addresses)) {
            return [];
        }

        $billingAddress = data_get($addresses, 'billing');

        return is_array($billingAddress) ? $billingAddress : [];
    }

    private function relations(): array
    {
        return [
            'order',
            'user',
            'returnReason',
            'lines.orderLine',
        ];
    }
}
