<?php

namespace PictaStudio\Venditio\Actions\Returns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteReturnRequest
{
    public function __construct(
        private readonly RecalculateOrderLineReturnState $recalculateOrderLineReturnState,
    ) {}

    public function handle(Model $returnRequest): void
    {
        DB::transaction(function () use ($returnRequest): void {
            $creditNote = $returnRequest->relationLoaded('creditNote')
                ? $returnRequest->creditNote
                : $returnRequest->creditNote()->first();

            if ($creditNote instanceof Model) {
                throw ValidationException::withMessages([
                    'return_request_id' => ['A credited return request cannot be deleted.'],
                ]);
            }

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
