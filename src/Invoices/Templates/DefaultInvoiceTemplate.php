<?php

namespace PictaStudio\Venditio\Invoices\Templates;

use Illuminate\Support\{Arr, Carbon};
use PictaStudio\Venditio\Contracts\InvoiceTemplateInterface;

class DefaultInvoiceTemplate implements InvoiceTemplateInterface
{
    public function key(): string
    {
        return 'default';
    }

    public function version(): ?string
    {
        return '2';
    }

    public function render(array $invoice): string
    {
        $locale = $this->resolveLocale($invoice);
        $labels = $this->labels($locale);
        $currencyCode = (string) Arr::get($invoice, 'currency_code', '');
        $totals = Arr::get($invoice, 'totals', []);
        $payments = Arr::get($invoice, 'payments', []);

        $taxBreakdownRows = collect(Arr::get($totals, 'tax_breakdown', []))
            ->map(function (array $taxRow) use ($currencyCode, $locale): string {
                $rate = (float) Arr::get($taxRow, 'rate', 0);
                $taxable = $this->formatMoney((float) Arr::get($taxRow, 'taxable', 0), $currencyCode, $locale);
                $amount = $this->formatMoney((float) Arr::get($taxRow, 'amount', 0), $currencyCode, $locale);

                return $this->renderSummaryRow(
                    $this->escape($this->taxLabel($rate, $taxable, $locale)),
                    $this->escape($amount)
                );
            })
            ->implode("\n");

        $lineRows = collect(Arr::get($invoice, 'lines', []))
            ->map(function (array $line) use ($currencyCode, $locale): string {
                $description = $this->escape((string) Arr::get($line, 'description'));
                $details = Arr::get($line, 'details');
                $qty = (int) Arr::get($line, 'qty', 0);
                $unitPrice = $this->escape($this->formatMoney((float) Arr::get($line, 'unit_price', 0), $currencyCode, $locale));
                $taxRate = $this->escape($this->formatPercent((float) Arr::get($line, 'tax_rate', 0), $locale));
                $amount = $this->escape($this->formatMoney((float) Arr::get($line, 'line_subtotal', 0), $currencyCode, $locale));
                $detailsHtml = filled($details)
                    ? '<div class="line-details">' . $this->escape((string) $details) . '</div>'
                    : '';

                return <<<HTML
                    <tr>
                        <td class="description-cell">
                            <div class="line-description">{$description}</div>
                            {$detailsHtml}
                        </td>
                        <td class="numeric-cell">{$qty}</td>
                        <td class="numeric-cell">{$unitPrice}</td>
                        <td class="numeric-cell">{$taxRate}</td>
                        <td class="numeric-cell">{$amount}</td>
                    </tr>
                HTML;
            })
            ->implode("\n");

        $paymentRows = collect($payments)
            ->map(function (array $payment) use ($currencyCode, $locale): string {
                $method = $this->escape((string) Arr::get($payment, 'method', ''));
                $paidAt = $this->escape($this->formatDate(Arr::get($payment, 'paid_at'), $locale));
                $amount = $this->escape($this->formatMoney((float) Arr::get($payment, 'amount', 0), $currencyCode, $locale));
                $reference = $this->escape((string) Arr::get($payment, 'reference', ''));

                return <<<HTML
                    <tr>
                        <td>{$method}</td>
                        <td>{$paidAt}</td>
                        <td>{$amount}</td>
                        <td>{$reference}</td>
                    </tr>
                HTML;
            })
            ->implode("\n");

        $paymentsSection = $paymentRows === ''
            ? ''
            : <<<HTML
                <section class="payments">
                    <h2>{$this->escape($labels['payment_history'])}</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>{$this->escape($labels['payment_method'])}</th>
                                <th>{$this->escape($labels['payment_date'])}</th>
                                <th>{$this->escape($labels['payment_amount'])}</th>
                                <th>{$this->escape($labels['payment_reference'])}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$paymentRows}
                        </tbody>
                    </table>
                </section>
            HTML;

        $billingBlock = $this->renderAddressBlock(Arr::get($invoice, 'billing_address', []));
        $sellerBlock = $this->renderAddressBlock(Arr::get($invoice, 'seller', []));
        $shippingAddress = Arr::get($invoice, 'shipping_address', []);

        $shippingBlock = $shippingAddress === []
            ? ''
            : <<<HTML
                <div class="address-panel address-panel-stacked">
                    <h2>{$this->escape($labels['shipping_to'])}</h2>
                    {$this->renderAddressBlock($shippingAddress)}
                </div>
            HTML;

        $orderReference = $this->escape((string) Arr::get($invoice, 'order_identifier', ''));
        $invoiceNumber = $this->escape((string) Arr::get($invoice, 'identifier', ''));
        $issuedAt = $this->escape($this->formatDate(Arr::get($invoice, 'issued_at'), $locale));

        $documentMetaRows = collect([
            $this->renderDocumentMetaRow($labels['invoice_number'], $invoiceNumber),
            $this->renderDocumentMetaRow($labels['order_reference'], $orderReference),
            $this->renderDocumentMetaRow($labels['issued_at'], $issuedAt),
        ])->implode("\n");

        $subTotal = $this->escape($this->formatMoney((float) Arr::get($totals, 'sub_total_taxable', 0), $currencyCode, $locale));
        $shippingFee = (float) Arr::get($totals, 'shipping_fee', 0);
        $paymentFee = (float) Arr::get($totals, 'payment_fee', 0);
        $discountAmount = (float) Arr::get($totals, 'discount_amount', 0);
        $totalFinal = $this->escape($this->formatMoney((float) Arr::get($totals, 'total_final', 0), $currencyCode, $locale));

        $shippingFeeRow = $shippingFee <= 0
            ? ''
            : $this->renderSummaryRow(
                $this->escape($labels['shipping_fee']),
                $this->escape($this->formatMoney($shippingFee, $currencyCode, $locale))
            );

        $paymentFeeRow = $paymentFee <= 0
            ? ''
            : $this->renderSummaryRow(
                $this->escape($labels['payment_fee']),
                $this->escape($this->formatMoney($paymentFee, $currencyCode, $locale))
            );

        $discountRow = $discountAmount <= 0
            ? ''
            : $this->renderSummaryRow(
                $this->escape($labels['discount']),
                '-' . $this->escape($this->formatMoney($discountAmount, $currencyCode, $locale))
            );

        $subTotalRow = $this->renderSummaryRow(
            $this->escape($labels['subtotal']),
            $subTotal
        );

        $totalRow = $this->renderSummaryRow(
            $this->escape($labels['total']),
            $totalFinal,
            'summary-total'
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="{$this->escape($locale)}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{$invoiceNumber}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.34;
            padding: 28px 34px 20px;
        }
        .page {
            width: 100%;
        }
        .header {
            border-bottom: 1px solid #d8dee8;
            margin-bottom: 16px;
            padding-bottom: 10px;
        }
        .eyebrow {
            color: #8992a5;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.12em;
            margin: 0 0 3px;
            text-transform: uppercase;
        }
        .title {
            color: #111827;
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 10px;
        }
        .document-meta,
        .address-grid,
        .line-items,
        .summary-table,
        .payments table {
            border-collapse: collapse;
            width: 100%;
        }
        .document-meta {
            margin-top: 2px;
            width: 100%;
        }
        .document-meta td {
            padding: 0 0 3px;
            vertical-align: top;
        }
        .document-meta-label {
            color: #111827;
            font-size: 10px;
            font-weight: 700;
            padding-right: 10px;
            white-space: nowrap;
            width: 142px;
        }
        .document-meta-value {
            color: #1f2937;
            font-size: 10px;
            line-height: 1.4;
            word-break: break-word;
        }
        .address-grid {
            margin-bottom: 12px;
            table-layout: fixed;
        }
        .address-cell {
            padding-right: 14px;
            vertical-align: top;
            width: 50%;
        }
        .address-cell:last-child {
            padding-right: 0;
        }
        .address-panel h2 {
            color: #111827;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0;
            margin: 0 0 4px;
            text-transform: none;
        }
        .address-panel-stacked {
            margin-top: 8px;
        }
        .meta-lines {
            font-size: 10px;
            line-height: 1.2;
        }
        .meta-lines div {
            margin-bottom: 1px;
        }
        .line-items thead th,
        .payments thead th {
            border-bottom: 1px solid #d8dee8;
            color: #6b7280;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0;
            padding: 0 0 6px;
            text-align: left;
            text-transform: none;
        }
        .line-items tbody td,
        .payments tbody td {
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 0;
            vertical-align: top;
        }
        .description-cell {
            padding-right: 14px;
            width: 44%;
        }
        .line-description {
            color: #111827;
            font-weight: 700;
        }
        .line-details {
            color: #6b7280;
            font-size: 9.5px;
            margin-top: 1px;
        }
        .numeric-cell {
            white-space: nowrap;
        }
        .summary-wrap {
            margin-top: 4px;
            width: 100%;
            page-break-inside: avoid;
        }
        .summary-box {
            margin-left: auto;
            width: 43%;
            page-break-inside: avoid;
        }
        .summary-table {
            border-top: 1px solid #d8dee8;
            page-break-inside: avoid;
        }
        .summary-table td {
            border-top: 1px solid #edf1f5;
            padding: 4px 0;
            vertical-align: top;
        }
        .summary-table tr:first-child td {
            border-top: 0;
        }
        .summary-label {
            color: #4b5563;
            font-size: 10.5px;
            padding-right: 16px;
        }
        .summary-value {
            color: #111827;
            font-size: 10.5px;
            text-align: right;
            white-space: nowrap;
        }
        .summary-total td {
            border-top: 1px solid #d4dae3;
            font-weight: 700;
            padding-top: 6px;
        }
        .summary-total .summary-label,
        .summary-total .summary-value {
            color: #111827;
            font-size: 11px;
        }
        .payments {
            margin-top: 22px;
            page-break-inside: avoid;
        }
        .payments h2 {
            color: #111827;
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .footer {
            color: #98a2b3;
            font-size: 9px;
            margin-top: 14px;
            padding-top: 2px;
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="header">
            <p class="eyebrow">{$this->escape($labels['document_label'])}</p>
            <h1 class="title">{$this->escape($labels['title'])}</h1>
            <table class="document-meta">
                {$documentMetaRows}
            </table>
        </header>

        <table class="address-grid">
            <tr>
                <td class="address-cell">
                    <div class="address-panel">
                        <h2>{$this->escape($labels['seller'])}</h2>
                        {$sellerBlock}
                    </div>
                </td>
                <td class="address-cell">
                    <div class="address-panel">
                        <h2>{$this->escape($labels['billing_to'])}</h2>
                        {$billingBlock}
                    </div>
                    {$shippingBlock}
                </td>
            </tr>
        </table>

        <table class="line-items">
            <thead>
                <tr>
                    <th>{$this->escape($labels['description'])}</th>
                    <th>{$this->escape($labels['qty'])}</th>
                    <th>{$this->escape($labels['unit_price'])}</th>
                    <th>{$this->escape($labels['taxes'])}</th>
                    <th>{$this->escape($labels['amount'])}</th>
                </tr>
            </thead>
            <tbody>
                {$lineRows}
            </tbody>
        </table>

        <section class="summary-wrap">
            <div class="summary-box">
                <table class="summary-table">
                    {$subTotalRow}
                    {$shippingFeeRow}
                    {$paymentFeeRow}
                    {$discountRow}
                    {$taxBreakdownRows}
                    {$totalRow}
                </table>
            </div>
        </section>

        {$paymentsSection}

        <footer class="footer">
            {$this->escape($labels['footer'])}
        </footer>
    </div>
</body>
</html>
HTML;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    protected function renderAddressBlock(array $address): string
    {
        $lines = collect([
            Arr::get($address, 'name'),
            mb_trim(implode(' ', array_filter([
                Arr::get($address, 'company_name'),
            ]))),
            mb_trim(implode(' ', array_filter([
                Arr::get($address, 'first_name'),
                Arr::get($address, 'last_name'),
            ]))),
            Arr::get($address, 'address_line_1'),
            Arr::get($address, 'address_line_2'),
            mb_trim(implode(' ', array_filter([
                Arr::get($address, 'postal_code'),
                Arr::get($address, 'zip'),
                Arr::get($address, 'city'),
            ]))),
            mb_trim(implode(' ', array_filter([
                Arr::get($address, 'state'),
                Arr::get($address, 'country'),
            ]))),
            Arr::get($address, 'vat_number'),
            Arr::get($address, 'tax_id'),
            Arr::get($address, 'email'),
            Arr::get($address, 'phone'),
        ])
            ->filter(fn (?string $line): bool => filled($line))
            ->map(fn (string $line): string => '<div>' . $this->escape($line) . '</div>')
            ->implode('');

        return '<div class="meta-lines">' . $lines . '</div>';
    }

    protected function renderDocumentMetaRow(string $label, string $value): string
    {
        return <<<HTML
            <tr>
                <td class="document-meta-label">{$this->escape($label)}</td>
                <td class="document-meta-value">{$value}</td>
            </tr>
        HTML;
    }

    protected function renderSummaryRow(string $label, string $value, string $rowClass = ''): string
    {
        $classAttribute = $rowClass === '' ? '' : ' class="' . $rowClass . '"';

        return <<<HTML
            <tr{$classAttribute}>
                <td class="summary-label">{$label}</td>
                <td class="summary-value">{$value}</td>
            </tr>
        HTML;
    }

    protected function taxLabel(float $rate, string $taxable, string $locale): string
    {
        $labels = $this->labels($locale);

        return str($labels['tax_row'])
            ->replace('{rate}', $this->formatPercent($rate, $locale))
            ->replace('{taxable}', $taxable)
            ->toString();
    }

    protected function formatMoney(float $amount, string $currencyCode, string $locale): string
    {
        $decimals = str_starts_with($locale, 'it')
            ? [',', '.']
            : ['.', ','];

        return number_format($amount, 2, $decimals[0], $decimals[1]) . ' ' . $currencyCode;
    }

    protected function formatPercent(float $rate, string $locale): string
    {
        $decimal = str_starts_with($locale, 'it') ? ',' : '.';

        return number_format($rate, floor($rate) === $rate ? 0 : 2, $decimal, '') . '%';
    }

    protected function formatDate(mixed $value, string $locale): string
    {
        if (blank($value)) {
            return '';
        }

        return Carbon::parse($value)
            ->locale($locale)
            ->translatedFormat('j F Y');
    }

    protected function resolveLocale(array $invoice): string
    {
        $locale = (string) Arr::get($invoice, 'locale', app()->getLocale());

        return str_starts_with($locale, 'it') ? 'it' : 'en';
    }

    /**
     * @return array<string, string>
     */
    protected function labels(string $locale): array
    {
        if ($locale === 'it') {
            return [
                'document_label' => 'Documento fiscale',
                'title' => 'Fattura',
                'invoice_number' => 'Numero fattura',
                'order_reference' => 'Riferimento ordine',
                'issued_at' => 'Data emissione',
                'seller' => 'Cedente',
                'billing_to' => 'Intestata a',
                'shipping_to' => 'Spedire a',
                'description' => 'Descrizione',
                'qty' => 'Q.tà',
                'unit_price' => 'Prezzo unitario',
                'taxes' => 'Imposte',
                'amount' => 'Importo',
                'subtotal' => 'Subtotale',
                'shipping_fee' => 'Spedizione',
                'payment_fee' => 'Commissione pagamento',
                'discount' => 'Sconto',
                'total' => 'Totale',
                'tax_row' => 'IVA ({rate} su {taxable})',
                'payment_history' => 'Cronologia pagamenti',
                'payment_method' => 'Metodo di pagamento',
                'payment_date' => 'Data',
                'payment_amount' => 'Importo pagato',
                'payment_reference' => 'Riferimento',
                'footer' => 'Documento generato da Venditio',
            ];
        }

        return [
            'document_label' => 'Tax document',
            'title' => 'Invoice',
            'invoice_number' => 'Invoice number',
            'order_reference' => 'Order reference',
            'issued_at' => 'Issued at',
            'seller' => 'Seller',
            'billing_to' => 'Bill to',
            'shipping_to' => 'Ship to',
            'description' => 'Description',
            'qty' => 'Qty',
            'unit_price' => 'Unit price',
            'taxes' => 'Taxes',
            'amount' => 'Amount',
            'subtotal' => 'Subtotal',
            'shipping_fee' => 'Shipping',
            'payment_fee' => 'Payment fee',
            'discount' => 'Discount',
            'total' => 'Total',
            'tax_row' => 'Tax ({rate} on {taxable})',
            'payment_history' => 'Payment history',
            'payment_method' => 'Payment method',
            'payment_date' => 'Date',
            'payment_amount' => 'Amount paid',
            'payment_reference' => 'Reference',
            'footer' => 'Document generated by Venditio',
        ];
    }

    protected function escape(string $value): string
    {
        return e($value);
    }
}
