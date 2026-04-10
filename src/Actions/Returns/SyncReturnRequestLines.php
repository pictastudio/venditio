<?php

namespace PictaStudio\Venditio\Actions\Returns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SyncReturnRequestLines
{
    /**
     * @param  array<int, array{order_line_id: int, qty: int}>  $lines
     * @return Collection<int, int>
     */
    public function handle(Model $returnRequest, array $lines): Collection
    {
        $normalizedLines = collect($lines)
            ->map(fn (array $line): array => [
                'order_line_id' => (int) $line['order_line_id'],
                'qty' => (int) $line['qty'],
            ])
            ->keyBy('order_line_id');

        $currentActiveOrderLineIds = $returnRequest->lines()
            ->pluck('order_line_id')
            ->map(fn (mixed $orderLineId): int => (int) $orderLineId);

        $existingLines = $returnRequest->lines()
            ->withTrashed()
            ->get()
            ->keyBy(fn (Model $returnRequestLine): int => (int) $returnRequestLine->order_line_id);

        $currentActiveOrderLineIds
            ->diff($normalizedLines->keys())
            ->each(function (int $orderLineId) use ($existingLines): void {
                $line = $existingLines->get($orderLineId);

                if (!$line instanceof Model || $line->trashed()) {
                    return;
                }

                $line->delete();
            });

        $normalizedLines->each(function (array $linePayload, int $orderLineId) use ($returnRequest, $existingLines): void {
            $existingLine = $existingLines->get($orderLineId);

            if ($existingLine instanceof Model) {
                if ($existingLine->trashed()) {
                    $existingLine->restore();
                }

                $existingLine->forceFill([
                    'qty' => $linePayload['qty'],
                ]);
                $existingLine->save();

                return;
            }

            $returnRequest->lines()->create($linePayload);
        });

        return $currentActiveOrderLineIds
            ->merge($normalizedLines->keys())
            ->map(fn (mixed $orderLineId): int => (int) $orderLineId)
            ->unique()
            ->values();
    }
}
