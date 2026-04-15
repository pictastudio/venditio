<?php

namespace PictaStudio\Venditio\Invoices;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\{InvoicePayloadFactoryInterface, InvoiceSellerResolverInterface};

class DefaultInvoicePayloadFactory implements InvoicePayloadFactoryInterface
{
    public function __construct(
        private readonly ?InvoiceSellerResolverInterface $sellerResolver = null,
    ) {}

    public function build(Model $order): array
    {
        $order->loadMissing(['lines.currency']);

        $lines = $order->getRelation('lines');

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'order' => ['The order must contain at least one line to generate an invoice.'],
            ]);
        }

        $billingAddress = $this->normalizeAddress(Arr::get($order->addresses, 'billing'));

        if ($billingAddress === []) {
            throw ValidationException::withMessages([
                'billing_address' => ['A billing address is required to generate an invoice.'],
            ]);
        }

        $seller = $this->resolveSeller();
        $currencyCode = $this->resolveCurrencyCode($lines->all());

        return [
            'currency_code' => $currencyCode,
            'seller' => $seller,
            'billing_address' => $billingAddress,
            'shipping_address' => $this->normalizeAddress(Arr::get($order->addresses, 'shipping')),
            'lines' => collect($lines)
                ->map(fn (Model $line): array => [
                    'product_id' => $line->product_id,
                    'description' => (string) $line->product_name,
                    'details' => filled($line->product_sku) ? 'SKU: ' . $line->product_sku : null,
                    'sku' => $line->product_sku,
                    'qty' => (int) $line->qty,
                    'unit_price' => (float) $line->unit_final_price_taxable,
                    'unit_tax' => (float) $line->unit_final_price_tax,
                    'tax_rate' => (float) $line->tax_rate,
                    'line_subtotal' => round((float) $line->unit_final_price_taxable * (int) $line->qty, 2),
                    'line_tax' => round((float) $line->unit_final_price_tax * (int) $line->qty, 2),
                    'line_total' => (float) $line->total_final_price,
                ])
                ->values()
                ->all(),
            'totals' => [
                'sub_total_taxable' => (float) $order->sub_total_taxable,
                'sub_total_tax' => (float) $order->sub_total_tax,
                'sub_total' => (float) $order->sub_total,
                'shipping_fee' => (float) $order->shipping_fee,
                'payment_fee' => (float) $order->payment_fee,
                'discount_amount' => (float) $order->discount_amount,
                'total_final' => (float) $order->total_final,
                'tax_breakdown' => collect($lines)
                    ->groupBy(fn (Model $line): string => (string) $line->tax_rate)
                    ->map(fn ($group, string $rate): array => [
                        'rate' => (float) $rate,
                        'taxable' => round($group->sum(fn (Model $line): float => (float) $line->unit_final_price_taxable * (int) $line->qty), 2),
                        'amount' => round($group->sum(fn (Model $line): float => (float) $line->unit_final_price_tax * (int) $line->qty), 2),
                    ])
                    ->values()
                    ->all(),
            ],
            'payments' => [],
        ];
    }

    /**
     * @param  array<int, Model>  $lines
     */
    protected function resolveCurrencyCode(array $lines): string
    {
        $currencyCodes = collect($lines)
            ->map(fn (Model $line): ?string => $line->currency?->code)
            ->values();

        if ($currencyCodes->contains(fn (?string $code): bool => blank($code))) {
            throw ValidationException::withMessages([
                'currency_code' => ['All order lines must have a currency before generating an invoice.'],
            ]);
        }

        $currencyCodes = $currencyCodes
            ->unique()
            ->values();

        if ($currencyCodes->count() !== 1) {
            throw ValidationException::withMessages([
                'currency_code' => ['Invoice generation requires all order lines to use the same currency.'],
            ]);
        }

        return (string) $currencyCodes->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveSeller(): array
    {
        $seller = ($this->sellerResolver ?? new DefaultInvoiceSellerResolver)->resolve();

        $required = [
            'name',
            'address_line_1',
            'city',
            'postal_code',
            'country',
        ];

        $missing = collect($required)
            ->reject(fn (string $key): bool => filled(Arr::get($seller, $key)));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(
                $missing
                    ->mapWithKeys(fn (string $key): array => [
                        'seller.' . $key => ['This seller field is required to generate an invoice.'],
                    ])
                    ->all()
            );
        }

        return $seller;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeAddress(mixed $address): array
    {
        if (!is_array($address)) {
            return [];
        }

        return collect($address)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();
    }
}
