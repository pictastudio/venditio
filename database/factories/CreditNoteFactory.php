<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\{CreditNote, Invoice, Order, ReturnReason};

class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        return [
            'order_id' => function (): int {
                return Order::factory()->create()->getKey();
            },
            'invoice_id' => function (array $attributes): int {
                return Invoice::factory()->create([
                    'order_id' => $attributes['order_id'],
                ])->getKey();
            },
            'return_request_id' => function (array $attributes): int {
                $returnReason = ReturnReason::factory()->create();
                $order = Order::query()->findOrFail($attributes['order_id']);

                return config('venditio.models.return_request')::query()->create([
                    'order_id' => $order->getKey(),
                    'return_reason_id' => $returnReason->getKey(),
                    'billing_address' => [
                        'first_name' => 'Factory',
                        'last_name' => 'Customer',
                        'address_line_1' => 'Factory Street 1',
                        'city' => 'Verona',
                    ],
                    'description' => 'Factory return request',
                    'is_accepted' => true,
                    'is_verified' => false,
                ])->getKey();
            },
            'identifier' => 'CN-' . fake()->unique()->numerify('####-##-######'),
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
            'references' => [
                'order_identifier' => 'ORD-FACTORY',
                'invoice_identifier' => 'INV-FACTORY',
                'return_request_id' => 1,
            ],
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
            'rendered_html' => '<html><body>Credit note</body></html>',
        ];
    }
}
