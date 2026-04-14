<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\{Invoice, Order};

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'identifier' => 'INV-' . fake()->unique()->numerify('####-##-######'),
            'issued_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'currency_code' => 'EUR',
            'template_key' => 'default',
            'template_version' => '1',
            'locale' => 'en',
            'paper' => 'a4',
            'orientation' => 'portrait',
            'seller' => [
                'name' => 'Venditio Seller',
                'address_line_1' => 'Seller street 1',
                'city' => 'Verona',
                'postal_code' => '37100',
                'country' => 'Italy',
            ],
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_line_1' => 'Customer street 1',
                'city' => 'Verona',
                'zip' => '37100',
                'country' => 'Italy',
            ],
            'shipping_address' => [],
            'lines' => [],
            'totals' => [
                'sub_total_taxable' => 10,
                'sub_total_tax' => 2.2,
                'sub_total' => 12.2,
                'shipping_fee' => 0,
                'payment_fee' => 0,
                'discount_amount' => 0,
                'total_final' => 12.2,
                'tax_breakdown' => [],
            ],
            'payments' => [],
            'rendered_html' => '<html><body>Invoice</body></html>',
        ];
    }
}
