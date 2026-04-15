<?php

namespace PictaStudio\Venditio\CreditNotes\Templates;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Contracts\CreditNoteTemplateInterface;
use PictaStudio\Venditio\Invoices\Templates\DefaultInvoiceTemplate;

class DefaultCreditNoteTemplate extends DefaultInvoiceTemplate implements CreditNoteTemplateInterface
{
    public function key(): string
    {
        return 'default';
    }

    public function version(): ?string
    {
        return '2';
    }

    public function render(array $creditNote): string
    {
        $locale = $this->resolveLocale($creditNote);
        $labels = $this->labels($locale);
        $currencyCode = (string) Arr::get($creditNote, 'currency_code', '');
        $references = Arr::get($creditNote, 'references', []);
        $totals = Arr::get($creditNote, 'totals', []);

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

        $lineRows = collect(Arr::get($creditNote, 'lines', []))
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

        $billingBlock = $this->renderAddressBlock(Arr::get($creditNote, 'billing_address', []));
        $sellerBlock = $this->renderAddressBlock(Arr::get($creditNote, 'seller', []));
        $shippingAddress = Arr::get($creditNote, 'shipping_address', []);

        $shippingBlock = $shippingAddress === []
            ? ''
            : <<<HTML
                <div class="address-panel address-panel-stacked">
                    <h2>{$this->escape($labels['shipping_to'])}</h2>
                    {$this->renderAddressBlock($shippingAddress)}
                </div>
            HTML;

        $creditNoteNumber = $this->escape((string) Arr::get($creditNote, 'identifier', ''));
        $orderReference = $this->escape((string) Arr::get($references, 'order_identifier', ''));
        $invoiceReference = $this->escape((string) Arr::get($references, 'invoice_identifier', ''));
        $returnRequestReference = $this->escape((string) Arr::get($references, 'return_request_id', ''));
        $issuedAt = $this->escape($this->formatDate(Arr::get($creditNote, 'issued_at'), $locale));

        $documentMetaRows = collect([
            $this->renderDocumentMetaRow($labels['credit_note_number'], $creditNoteNumber),
            $this->renderDocumentMetaRow($labels['order_reference'], $orderReference),
            $this->renderDocumentMetaRow($labels['invoice_reference'], $invoiceReference),
            $this->renderDocumentMetaRow($labels['return_request_reference'], $returnRequestReference),
            $this->renderDocumentMetaRow($labels['issued_at'], $issuedAt),
        ])->implode("\n");

        $subTotal = $this->escape($this->formatMoney((float) Arr::get($totals, 'sub_total_taxable', 0), $currencyCode, $locale));
        $totalFinal = $this->escape($this->formatMoney((float) Arr::get($totals, 'total_final', 0), $currencyCode, $locale));

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
    <title>{$creditNoteNumber}</title>
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
                    {$taxBreakdownRows}
                    {$totalRow}
                </table>
            </div>
        </section>

        <footer class="footer">
            {$this->escape($labels['footer'])}
        </footer>
    </div>
</body>
</html>
HTML;
    }

    /**
     * @return array<string, string>
     */
    protected function labels(string $locale): array
    {
        if ($locale === 'it') {
            return [
                'document_label' => 'Documento fiscale',
                'title' => 'Nota di credito',
                'credit_note_number' => 'Numero nota di credito',
                'order_reference' => 'Riferimento ordine',
                'invoice_reference' => 'Riferimento fattura',
                'return_request_reference' => 'Riferimento reso',
                'issued_at' => 'Data emissione',
                'seller' => 'Cedente',
                'billing_to' => 'Intestata a',
                'shipping_to' => 'Spedire a',
                'description' => 'Descrizione',
                'qty' => 'Q.tà',
                'unit_price' => 'Prezzo unitario',
                'taxes' => 'Imposte',
                'amount' => 'Importo',
                'subtotal' => 'Subtotale accreditato',
                'total' => 'Totale accreditato',
                'tax_row' => 'IVA ({rate} su {taxable})',
                'footer' => 'Documento generato da Venditio',
            ];
        }

        return [
            'document_label' => 'Tax document',
            'title' => 'Credit note',
            'credit_note_number' => 'Credit note number',
            'order_reference' => 'Order reference',
            'invoice_reference' => 'Invoice reference',
            'return_request_reference' => 'Return request reference',
            'issued_at' => 'Issued at',
            'seller' => 'Seller',
            'billing_to' => 'Bill to',
            'shipping_to' => 'Ship to',
            'description' => 'Description',
            'qty' => 'Qty',
            'unit_price' => 'Unit price',
            'taxes' => 'Taxes',
            'amount' => 'Amount',
            'subtotal' => 'Subtotal credited',
            'total' => 'Total credited',
            'tax_row' => 'Tax ({rate} on {taxable})',
            'footer' => 'Document generated by Venditio',
        ];
    }
}
