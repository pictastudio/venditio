<?php

namespace PictaStudio\Venditio\Actions\Returns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeleteReturnRequest
{
    public function __construct(
        private readonly RecalculateOrderLineReturnState $recalculateOrderLineReturnState,
    ) {}

    public function handle(Model $returnRequest): void
    {
        DB::transaction(function () use ($returnRequest): void {
            $touchedOrderLineIds = $returnRequest->lines()
                ->pluck('order_line_id')
                ->map(fn (mixed $orderLineId): int => (int) $orderLineId)
                ->values();

            $returnRequest->lines()->delete();
            $returnRequest->delete();

            $this->recalculateOrderLineReturnState->handle($touchedOrderLineIds);
        });
    }
}
