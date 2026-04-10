<?php

namespace PictaStudio\Venditio\Actions\Returns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateReturnRequest
{
    public function __construct(
        private readonly SyncReturnRequestLines $syncReturnRequestLines,
        private readonly RecalculateOrderLineReturnState $recalculateOrderLineReturnState,
    ) {}

    public function handle(Model $returnRequest, array $payload): Model
    {
        return DB::transaction(function () use ($returnRequest, $payload): Model {
            $linesWereProvided = array_key_exists('lines', $payload);
            $acceptedBefore = (bool) $returnRequest->is_accepted;
            $lines = Arr::pull($payload, 'lines', []);

            $returnRequest->fill($payload);
            $returnRequest->save();

            $touchedOrderLineIds = collect();

            if ($linesWereProvided) {
                $touchedOrderLineIds = $this->syncReturnRequestLines->handle($returnRequest, $lines);
            }

            if ($acceptedBefore !== (bool) $returnRequest->is_accepted && !$linesWereProvided) {
                $touchedOrderLineIds = $returnRequest->lines()
                    ->pluck('order_line_id')
                    ->map(fn (mixed $orderLineId): int => (int) $orderLineId)
                    ->values();
            }

            $this->recalculateOrderLineReturnState->handle($touchedOrderLineIds);

            return $returnRequest->refresh()->load($this->relations());
        });
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
