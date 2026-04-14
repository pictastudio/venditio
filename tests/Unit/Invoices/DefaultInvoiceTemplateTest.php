<?php

use Barryvdh\DomPDF\Facade\Pdf;
use PictaStudio\Venditio\Invoices\Templates\DefaultInvoiceTemplate;

it('renders compact header, address blocks, and totals summary', function () {
    $template = new DefaultInvoiceTemplate;

    $html = $template->render(defaultInvoiceTemplatePayload());

    expect($template->version())->toBe('2')
        ->and($html)->toContain('class="document-meta"')
        ->toContain('class="document-meta-label">Numero fattura</td>')
        ->toContain('class="document-meta-label">Riferimento ordine</td>')
        ->toContain('class="document-meta-label">Data emissione</td>')
        ->toContain('class="address-grid"')
        ->toContain('>Cedente<')
        ->toContain('>Intestata a<')
        ->toContain('>Spedire a<')
        ->toContain('class="summary-box"')
        ->toContain('class="summary-table"')
        ->toContain('Commissione pagamento')
        ->toContain('class="summary-total"')
        ->toContain('SKU: SKU-20260414164235-47-RC2R')
        ->not->toContain('Cronologia pagamenti');
});

it('renders payment history only when payments are present', function () {
    $template = new DefaultInvoiceTemplate;

    $html = $template->render(defaultInvoiceTemplatePayload([
        'identifier' => 'INV-2026-000002',
        'order_identifier' => 'ORD-20260414164235-0015',
        'shipping_address' => [],
        'totals' => [
            'payment_fee' => 0,
            'total_final' => 11.88,
            'tax_breakdown' => [],
        ],
        'payments' => [
            [
                'method' => 'Visa - 8210',
                'paid_at' => '2026-04-14 16:45:24',
                'amount' => 11.88,
                'reference' => 'RCPT-2839-1362',
            ],
        ],
    ]));

    expect($html)->toContain('Cronologia pagamenti')
        ->toContain('Visa - 8210')
        ->toContain('RCPT-2839-1362');
});

it('renders the default invoice sample on a single a4 page', function () {
    $template = new DefaultInvoiceTemplate;
    $html = $template->render(defaultInvoiceTemplatePayload());

    $pdf = Pdf::loadHTML($html)
        ->setPaper('a4', 'portrait')
        ->setWarnings(false);

    $pdf->output();

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function defaultInvoiceTemplatePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'identifier' => 'INV-2026-000001',
        'order_identifier' => 'ORD-20260414164235-0014',
        'issued_at' => '2026-04-14 16:45:24',
        'locale' => 'it',
        'currency_code' => 'EUR',
        'seller' => [
            'name' => 'Dental Today',
            'address_line_1' => 'Via Roma 123',
            'city' => 'Roma',
            'postal_code' => '12345',
            'state' => 'RM',
            'country' => 'Italy',
            'vat_number' => 'IT1234567890',
            'tax_id' => 'IT1234567890',
            'email' => 'info@dentaltoday.it',
            'phone' => '+39 1234567890',
        ],
        'billing_address' => [
            'first_name' => 'Jayda',
            'last_name' => 'Swift',
            'address_line_1' => '7905 Margarete Branch',
            'city' => 'Enna',
            'zip' => '16100',
            'country' => 'Italy',
            'tax_id' => '56218001137',
            'email' => 'mohr.antoinette@example.com',
            'phone' => '626.573.4910',
        ],
        'shipping_address' => [
            'first_name' => 'Jayda',
            'last_name' => 'Swift',
            'address_line_1' => '58913 Carol Run',
            'address_line_2' => 'Apt. 104',
            'city' => 'Enna',
            'zip' => '16100',
            'country' => 'Italy',
            'tax_id' => '23600949758',
            'email' => 'mohr.antoinette@example.com',
            'phone' => '626.573.4910',
        ],
        'lines' => [
            [
                'description' => 'Product 47 CCUOO',
                'details' => 'SKU: SKU-20260414164235-47-RC2R',
                'qty' => 1,
                'unit_price' => 9.74,
                'tax_rate' => 22,
                'line_subtotal' => 9.74,
            ],
        ],
        'totals' => [
            'sub_total_taxable' => 9.74,
            'shipping_fee' => 0,
            'payment_fee' => 6.21,
            'discount_amount' => 0,
            'total_final' => 18.09,
            'tax_breakdown' => [
                [
                    'rate' => 22,
                    'taxable' => 9.74,
                    'amount' => 2.14,
                ],
            ],
        ],
        'payments' => [],
    ], $overrides);
}
