<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ReturnRequest\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Collection, Fluent};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

trait ValidatesReturnRequestPayload
{
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $returnRequest = $this->route('return_request');
            $order = $this->resolveOrder($returnRequest);

            $this->ensureReturnRequestIsNotCredited($validator, $returnRequest);
            $this->ensureOrderHasBillingAddress($validator, $order);
            $this->ensureVerifiedRequestsAreAccepted($validator, $returnRequest);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->ensureLinesBelongToOrder($validator, $order);
            $this->ensureLinesDoNotExceedAvailableQuantity($validator, $returnRequest);
        });
    }

    private function ensureOrderHasBillingAddress($validator, ?Model $order): void
    {
        if ($order && filled($this->extractBillingAddress($order))) {
            return;
        }

        $validator->errors()->add(
            'order_id',
            'The selected order must have a billing address snapshot before a return request can be created or updated.'
        );
    }

    private function ensureReturnRequestIsNotCredited($validator, ?Model $returnRequest): void
    {
        if (!$returnRequest instanceof Model) {
            return;
        }

        $creditNote = $returnRequest->relationLoaded('creditNote')
            ? $returnRequest->creditNote
            : $returnRequest->creditNote()->first();

        if (!$creditNote instanceof Model) {
            return;
        }

        $validator->errors()->add(
            'return_request_id',
            'A credited return request cannot be updated.'
        );
    }

    private function ensureVerifiedRequestsAreAccepted($validator, ?Model $returnRequest): void
    {
        $isAccepted = $this->booleanInputOrFallback('is_accepted', (bool) ($returnRequest?->is_accepted ?? false));
        $isVerified = $this->booleanInputOrFallback('is_verified', (bool) ($returnRequest?->is_verified ?? false));

        if (!$isVerified || $isAccepted) {
            return;
        }

        $validator->errors()->add(
            'is_verified',
            'A return request cannot be verified unless it has been accepted.'
        );
    }

    private function ensureLinesBelongToOrder($validator, ?Model $order): void
    {
        $lines = $this->input('lines');

        if (!is_array($lines) || !$order) {
            return;
        }

        $orderLineIds = collect($lines)
            ->pluck('order_line_id')
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($orderLineIds->isEmpty()) {
            return;
        }

        $orderLineModel = resolve_model('order_line');
        $orderIdByOrderLineId = $orderLineModel::query()
            ->whereKey($orderLineIds->all())
            ->pluck('order_id', 'id');

        foreach ($lines as $index => $line) {
            $orderLineId = (int) ($line['order_line_id'] ?? 0);

            if ($orderLineId < 1) {
                continue;
            }

            if ((int) ($orderIdByOrderLineId[$orderLineId] ?? 0) === (int) $order->getKey()) {
                continue;
            }

            $validator->errors()->add(
                "lines.{$index}.order_line_id",
                'The selected order line does not belong to the selected order.'
            );
        }
    }

    private function ensureLinesDoNotExceedAvailableQuantity($validator, ?Model $returnRequest): void
    {
        $lines = $this->input('lines');

        if (!is_array($lines)) {
            return;
        }

        $requestedQtyByOrderLineId = collect($lines)
            ->mapWithKeys(fn (array $line): array => [
                (int) ($line['order_line_id'] ?? 0) => (int) ($line['qty'] ?? 0),
            ])
            ->filter(fn (int $qty, int $orderLineId): bool => $orderLineId > 0 && $qty > 0);

        if ($requestedQtyByOrderLineId->isEmpty()) {
            return;
        }

        $orderLineModel = resolve_model('order_line');
        $orderLineQtyById = $orderLineModel::query()
            ->whereKey($requestedQtyByOrderLineId->keys()->all())
            ->pluck('qty', 'id');

        $alreadyRequestedQtyByOrderLineId = $this->existingRequestedQtyByOrderLineId(
            $requestedQtyByOrderLineId->keys(),
            $returnRequest?->getKey(),
        );

        foreach ($lines as $index => $line) {
            $orderLineId = (int) ($line['order_line_id'] ?? 0);
            $requestedQty = (int) ($line['qty'] ?? 0);

            if ($orderLineId < 1 || $requestedQty < 1) {
                continue;
            }

            $orderedQty = (int) ($orderLineQtyById[$orderLineId] ?? 0);
            $alreadyRequestedQty = (int) ($alreadyRequestedQtyByOrderLineId[$orderLineId] ?? 0);
            $availableQty = max(0, $orderedQty - $alreadyRequestedQty);

            if ($requestedQty <= $availableQty) {
                continue;
            }

            $validator->errors()->add(
                "lines.{$index}.qty",
                "The requested return quantity for order line {$orderLineId} exceeds the available quantity."
            );
        }
    }

    /**
     * @param  Collection<int, int>  $orderLineIds
     * @return Collection<int, int>
     */
    private function existingRequestedQtyByOrderLineId(Collection $orderLineIds, mixed $ignoreReturnRequestId = null): Collection
    {
        $returnRequestLineModel = resolve_model('return_request_line');
        $returnRequestModel = resolve_model('return_request');
        $returnRequestLineTable = (new $returnRequestLineModel)->getTable();
        $returnRequestTable = (new $returnRequestModel)->getTable();

        return $returnRequestLineModel::query()
            ->selectRaw("{$returnRequestLineTable}.order_line_id as order_line_id")
            ->selectRaw("COALESCE(SUM({$returnRequestLineTable}.qty), 0) as requested_qty")
            ->join(
                $returnRequestTable,
                "{$returnRequestTable}.id",
                '=',
                "{$returnRequestLineTable}.return_request_id"
            )
            ->whereNull("{$returnRequestTable}.deleted_at")
            ->whereIn("{$returnRequestLineTable}.order_line_id", $orderLineIds->all())
            ->when(
                filled($ignoreReturnRequestId),
                fn ($query) => $query->where("{$returnRequestLineTable}.return_request_id", '!=', $ignoreReturnRequestId)
            )
            ->groupBy("{$returnRequestLineTable}.order_line_id")
            ->pluck('requested_qty', 'order_line_id')
            ->map(fn (mixed $qty): int => (int) $qty);
    }

    private function resolveOrder(?Model $returnRequest): ?Model
    {
        $orderId = array_key_exists('order_id', $this->all())
            ? (int) $this->input('order_id')
            : (int) ($returnRequest?->order_id ?? 0);

        if ($orderId < 1) {
            return null;
        }

        $orderModel = resolve_model('order');

        return $orderModel::query()->find($orderId);
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

    private function booleanInputOrFallback(string $key, bool $fallback): bool
    {
        if (!array_key_exists($key, $this->all())) {
            return $fallback;
        }

        return $this->boolean($key);
    }
}
