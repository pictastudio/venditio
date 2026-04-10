<?php

namespace PictaStudio\Venditio\Actions\Returns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class RecalculateOrderLineReturnState
{
    public function handle(array|Collection $orderLineIds): void
    {
        $orderLineIds = collect($orderLineIds)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($orderLineIds->isEmpty()) {
            return;
        }

        $aggregates = $this->aggregateOrderLineReturns($orderLineIds);
        $orderLineModel = resolve_model('order_line');

        $orderLineModel::query()
            ->withTrashed()
            ->whereKey($orderLineIds->all())
            ->get()
            ->each(function (Model $orderLine) use ($aggregates): void {
                $aggregate = $aggregates->get((int) $orderLine->getKey());
                $requestedReturnQty = (int) ($aggregate['requested_return_qty'] ?? 0);
                $returnedQty = (int) ($aggregate['returned_qty'] ?? 0);
                $qty = (int) ($orderLine->qty ?? 0);
                $payload = [
                    'requested_return_qty' => $requestedReturnQty,
                    'returned_qty' => $returnedQty,
                    'has_return_requests' => $requestedReturnQty > 0,
                    'is_returned' => $returnedQty > 0,
                    'is_fully_returned' => $qty > 0 && $returnedQty >= $qty,
                ];

                if (!$this->hasChanged($orderLine, $payload)) {
                    return;
                }

                $orderLine->forceFill($payload);
                $orderLine->save();
            });
    }

    /**
     * @param  Collection<int, int>  $orderLineIds
     * @return Collection<int, array{requested_return_qty: int, returned_qty: int}>
     */
    private function aggregateOrderLineReturns(Collection $orderLineIds): Collection
    {
        $returnRequestLineModel = resolve_model('return_request_line');
        $returnRequestModel = resolve_model('return_request');
        $returnRequestLineTable = (new $returnRequestLineModel)->getTable();
        $returnRequestTable = (new $returnRequestModel)->getTable();

        return $returnRequestLineModel::query()
            ->selectRaw("{$returnRequestLineTable}.order_line_id as order_line_id")
            ->selectRaw("COALESCE(SUM({$returnRequestLineTable}.qty), 0) as requested_return_qty")
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$returnRequestTable}.is_accepted = 1 THEN {$returnRequestLineTable}.qty ELSE 0 END), 0) as returned_qty"
            )
            ->join(
                $returnRequestTable,
                "{$returnRequestTable}.id",
                '=',
                "{$returnRequestLineTable}.return_request_id"
            )
            ->whereNull("{$returnRequestTable}.deleted_at")
            ->whereIn("{$returnRequestLineTable}.order_line_id", $orderLineIds->all())
            ->groupBy("{$returnRequestLineTable}.order_line_id")
            ->get()
            ->mapWithKeys(fn (Model $aggregate): array => [
                (int) $aggregate->order_line_id => [
                    'requested_return_qty' => (int) $aggregate->requested_return_qty,
                    'returned_qty' => (int) $aggregate->returned_qty,
                ],
            ]);
    }

    private function hasChanged(Model $orderLine, array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if ($orderLine->getAttribute($key) !== $value) {
                return true;
            }
        }

        return false;
    }
}
