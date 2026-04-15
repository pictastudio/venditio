<?php

namespace PictaStudio\Venditio\CreditNotes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\CreditNotePayloadFactoryInterface;

class DefaultCreditNotePayloadFactory implements CreditNotePayloadFactoryInterface
{
    public function build(Model $order, Model $invoice, Model $returnRequest): array
    {
        $returnRequest->loadMissing(['lines.orderLine.currency']);

        $returnLines = $returnRequest->getRelation('lines');

        if ($returnLines->isEmpty()) {
            throw ValidationException::withMessages([
                'return_request_id' => ['The return request must contain at least one line to generate a credit note.'],
            ]);
        }

        $currencyCode = $this->resolveCurrencyCode($returnLines->all());
        $invoiceCurrencyCode = (string) ($invoice->currency_code ?? '');

        if ($invoiceCurrencyCode !== '' && $currencyCode !== $invoiceCurrencyCode) {
            throw ValidationException::withMessages([
                'currency_code' => ['Credit note generation requires returned lines to use the same currency as the related invoice.'],
            ]);
        }

        $lines = collect($returnLines)
            ->map(fn (Model $returnLine): array => $this->buildLinePayload($returnLine))
            ->values();

        $subTotalTaxable = round($lines->sum('line_subtotal'), 2);
        $subTotalTax = round($lines->sum('line_tax'), 2);
        $subTotal = round($subTotalTaxable + $subTotalTax, 2);

        return [
            'currency_code' => $currencyCode,
            'seller' => $this->normalizeArray($invoice->seller),
            'billing_address' => $this->normalizeArray($invoice->billing_address),
            'shipping_address' => $this->normalizeArray($invoice->shipping_address),
            'references' => [
                'order_identifier' => (string) ($order->identifier ?? ''),
                'invoice_identifier' => (string) ($invoice->identifier ?? ''),
                'return_request_id' => (int) $returnRequest->getKey(),
            ],
            'lines' => $lines->all(),
            'totals' => [
                'sub_total_taxable' => $subTotalTaxable,
                'sub_total_tax' => $subTotalTax,
                'sub_total' => $subTotal,
                'shipping_fee' => 0.0,
                'payment_fee' => 0.0,
                'discount_amount' => 0.0,
                'total_final' => $subTotal,
                'tax_breakdown' => $lines
                    ->groupBy(fn (array $line): string => (string) $line['tax_rate'])
                    ->map(fn ($group, string $rate): array => [
                        'rate' => (float) $rate,
                        'taxable' => round($group->sum('line_subtotal'), 2),
                        'amount' => round($group->sum('line_tax'), 2),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function buildLinePayload(Model $returnLine): array
    {
        $orderLine = $returnLine->orderLine;

        if (!$orderLine instanceof Model) {
            throw ValidationException::withMessages([
                'return_request_id' => ['All credited lines must reference an existing order line.'],
            ]);
        }

        $qty = (int) $returnLine->qty;
        $unitPrice = (float) $orderLine->unit_final_price_taxable;
        $unitTax = (float) $orderLine->unit_final_price_tax;
        $lineSubtotal = round($unitPrice * $qty, 2);
        $lineTax = round($unitTax * $qty, 2);

        return [
            'order_line_id' => (int) $orderLine->getKey(),
            'product_id' => $orderLine->product_id,
            'description' => (string) $orderLine->product_name,
            'details' => filled($orderLine->product_sku) ? 'SKU: ' . $orderLine->product_sku : null,
            'sku' => $orderLine->product_sku,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'unit_tax' => $unitTax,
            'tax_rate' => (float) $orderLine->tax_rate,
            'line_subtotal' => $lineSubtotal,
            'line_tax' => $lineTax,
            'line_total' => round($lineSubtotal + $lineTax, 2),
        ];
    }

    /**
     * @param  array<int, Model>  $returnLines
     */
    protected function resolveCurrencyCode(array $returnLines): string
    {
        $currencyCodes = collect($returnLines)
            ->map(function (Model $returnLine): ?string {
                $orderLine = $returnLine->orderLine;

                if (!$orderLine instanceof Model) {
                    return null;
                }

                return $orderLine->currency?->code;
            })
            ->values();

        if ($currencyCodes->contains(fn (?string $code): bool => blank($code))) {
            throw ValidationException::withMessages([
                'currency_code' => ['All credited lines must have a currency before generating a credit note.'],
            ]);
        }

        $currencyCodes = $currencyCodes
            ->unique()
            ->values();

        if ($currencyCodes->count() !== 1) {
            throw ValidationException::withMessages([
                'currency_code' => ['Credit note generation requires all credited lines to use the same currency.'],
            ]);
        }

        return (string) $currencyCodes->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->reject(fn (mixed $item, string|int $key): bool => $key === '' || $item === null || $item === '')
            ->all();
    }
}
