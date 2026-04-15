<?php

use PictaStudio\Venditio\CreditNotes\Templates\DefaultCreditNoteTemplate;

it('renders credit notes with the same compact document structure as invoices', function () {
    $template = new DefaultCreditNoteTemplate;

    $html = $template->render(defaultCreditNoteTemplatePayload());

    expect($template->version())->toBe('2')
        ->and($html)->toContain('class="document-meta"')
        ->toContain('class="document-meta-label">Numero nota di credito</td>')
        ->toContain('class="document-meta-label">Riferimento ordine</td>')
        ->toContain('class="document-meta-label">Riferimento fattura</td>')
        ->toContain('class="document-meta-label">Riferimento reso</td>')
        ->toContain('class="document-meta-label">Data emissione</td>')
        ->toContain('class="address-grid"')
        ->toContain('>Cedente<')
        ->toContain('>Intestata a<')
        ->toContain('>Spedire a<')
        ->toContain('class="line-items"')
        ->toContain('class="summary-box"')
        ->toContain('class="summary-table"')
        ->toContain('class="summary-total"')
        ->toContain('SKU: SKU-CN-001');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function defaultCreditNoteTemplatePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'identifier' => 'CN-2026-000001',
        'issued_at' => '2026-04-15 10:30:00',
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
        ],
        'references' => [
            'order_identifier' => 'ORD-202604150001',
            'invoice_identifier' => 'INV-202604150001',
            'return_request_id' => 42,
        ],
        'lines' => [
            [
                'description' => 'Returned product',
                'details' => 'SKU: SKU-CN-001',
                'qty' => 1,
                'unit_price' => 9.74,
                'tax_rate' => 22,
                'line_subtotal' => 9.74,
            ],
        ],
        'totals' => [
            'sub_total_taxable' => 9.74,
            'total_final' => 11.88,
            'tax_breakdown' => [
                [
                    'rate' => 22,
                    'taxable' => 9.74,
                    'amount' => 2.14,
                ],
            ],
        ],
    ], $overrides);
}
