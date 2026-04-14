<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Gate, Route};
use PictaStudio\Venditio\Contracts\{InvoiceNumberGeneratorInterface, InvoicePayloadFactoryInterface, InvoicePdfRendererInterface, InvoiceTemplateInterface};
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Currency, Invoice, Order, Product, TaxClass};

use function Pest\Laravel\{get, getJson, postJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'venditio.invoices.enabled' => true,
        'venditio.invoices.number_generator' => PictaStudio\Venditio\Generators\InvoiceNumberGenerator::class,
        'venditio.invoices.payload_factory' => PictaStudio\Venditio\Invoices\DefaultInvoicePayloadFactory::class,
        'venditio.invoices.template' => PictaStudio\Venditio\Invoices\Templates\DefaultInvoiceTemplate::class,
        'venditio.invoices.renderer' => PictaStudio\Venditio\Invoices\Renderers\DompdfInvoicePdfRenderer::class,
        'venditio.invoices.locale' => 'it',
        'venditio.invoices.filename_pattern' => 'invoice-{identifier}.pdf',
        'venditio.invoices.seller' => [
            'name' => 'Venditio SRL',
            'address_line_1' => 'Via Sicilia 76',
            'city' => 'Verona',
            'postal_code' => '37138',
            'country' => 'Italy',
            'tax_id' => 'IT12345678901',
            'email' => 'billing@example.test',
        ],
    ]);
});

it('registers invoice routes when invoices are enabled', function () {
    $routePrefix = mb_rtrim((string) config('venditio.routes.api.v1.name'), '.');

    expect(Route::has($routePrefix . '.orders.invoice.store'))->toBeTrue()
        ->and(Route::has($routePrefix . '.orders.invoice.show'))->toBeTrue()
        ->and(Route::has($routePrefix . '.orders.invoice.pdf'))->toBeTrue();
});

it('creates, returns, and downloads a persisted invoice pdf', function () {
    $order = createInvoiceReadyOrder();
    $prefix = config('venditio.routes.api.v1.prefix');

    $createResponse = postJson($prefix . '/orders/' . $order->getKey() . '/invoice')
        ->assertCreated()
        ->assertJsonPath('order_id', $order->getKey())
        ->assertJsonPath('currency_code', 'USD')
        ->assertJsonPath('template_key', 'default');

    $invoiceId = $createResponse->json('id');
    $invoice = Invoice::query()->findOrFail($invoiceId);

    expect($invoice->template_version)->toBe('2')
        ->and($invoice->rendered_html)
        ->toContain('Venditio SRL')
        ->toContain('Test line item')
        ->toContain((string) $invoice->identifier);

    getJson($prefix . '/orders/' . $order->getKey() . '/invoice')
        ->assertOk()
        ->assertJsonPath('id', $invoiceId)
        ->assertJsonPath('pdf_download_url', route(mb_rtrim((string) config('venditio.routes.api.v1.name'), '.') . '.orders.invoice.pdf', [
            'order' => $order->getKey(),
        ]));

    $downloadResponse = get($prefix . '/orders/' . $order->getKey() . '/invoice/pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="invoice-' . $invoice->identifier . '.pdf"');

    expect($downloadResponse->getContent())->toStartWith('%PDF-');
});

it('returns the existing invoice on repeated creation requests', function () {
    $order = createInvoiceReadyOrder();
    $prefix = config('venditio.routes.api.v1.prefix');

    $firstInvoiceId = postJson($prefix . '/orders/' . $order->getKey() . '/invoice')
        ->assertCreated()
        ->json('id');

    postJson($prefix . '/orders/' . $order->getKey() . '/invoice')
        ->assertOk()
        ->assertJsonPath('id', $firstInvoiceId);

    expect(Invoice::query()->count())->toBe(1);
});

it('keeps invoice snapshots immutable after the order changes', function () {
    $order = createInvoiceReadyOrder();
    $prefix = config('venditio.routes.api.v1.prefix');

    $invoiceId = postJson($prefix . '/orders/' . $order->getKey() . '/invoice')
        ->assertCreated()
        ->json('id');

    $invoiceBefore = Invoice::query()->findOrFail($invoiceId);
    $htmlBefore = $invoiceBefore->rendered_html;

    $order->forceFill([
        'user_first_name' => 'Changed',
        'addresses' => [
            'billing' => [
                'first_name' => 'Changed',
                'last_name' => 'Customer',
                'address_line_1' => 'Changed Street 1',
                'city' => 'Milan',
                'zip' => '20100',
                'country' => 'Italy',
                'email' => 'changed@example.test',
            ],
        ],
    ])->save();

    getJson($prefix . '/orders/' . $order->getKey() . '/invoice')
        ->assertOk()
        ->assertJsonPath('billing_address.city', 'Verona')
        ->assertJsonPath('billing_address.first_name', 'Invoice');

    $invoiceAfter = Invoice::query()->findOrFail($invoiceId);

    expect($invoiceAfter->rendered_html)->toBe($htmlBefore);
});

it('uses configured invoice generator, payload factory, template, and renderer overrides', function () {
    config([
        'venditio.invoices.number_generator' => TestInvoiceNumberGenerator::class,
        'venditio.invoices.payload_factory' => TestInvoicePayloadFactory::class,
        'venditio.invoices.template' => TestInvoiceTemplate::class,
        'venditio.invoices.renderer' => TestInvoicePdfRenderer::class,
        'venditio.invoices.paper' => 'letter',
    ]);

    app()->forgetInstance(InvoiceNumberGeneratorInterface::class);
    app()->forgetInstance(InvoicePayloadFactoryInterface::class);
    app()->forgetInstance(InvoiceTemplateInterface::class);
    app()->forgetInstance(InvoicePdfRendererInterface::class);

    $order = createInvoiceReadyOrder();
    $prefix = config('venditio.routes.api.v1.prefix');

    $invoiceId = postJson($prefix . '/orders/' . $order->getKey() . '/invoice')
        ->assertCreated()
        ->assertJsonPath('identifier', 'INV-CUSTOM-001')
        ->assertJsonPath('template_key', 'custom-test')
        ->json('id');

    $invoice = Invoice::query()->findOrFail($invoiceId);

    expect($invoice->rendered_html)->toContain('CUSTOM TEMPLATE INV-CUSTOM-001');

    $downloadResponse = get($prefix . '/orders/' . $order->getKey() . '/invoice/pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($downloadResponse->getContent())
        ->toContain('%PDF-CUSTOM%')
        ->toContain('letter');
});

it('honors invoice model policies for create and view endpoints', function () {
    config([
        'venditio.authorize_using_policies' => true,
        'venditio.models.invoice' => TestInvoiceModelOverride::class,
    ]);

    Gate::policy(TestInvoiceModelOverride::class, TestInvoicePolicy::class);

    $order = createInvoiceReadyOrder();
    $prefix = config('venditio.routes.api.v1.prefix');

    postJson($prefix . '/orders/' . $order->getKey() . '/invoice')->assertForbidden();

    config()->set('venditio.authorize_using_policies', false);
    $invoiceId = postJson($prefix . '/orders/' . $order->getKey() . '/invoice')->assertCreated()->json('id');
    config()->set('venditio.authorize_using_policies', true);

    getJson($prefix . '/orders/' . $order->getKey() . '/invoice')
        ->assertForbidden();

    expect(TestInvoiceModelOverride::query()->find($invoiceId))->not->toBeNull();
});

it('returns 422 when seller configuration is missing', function () {
    config(['venditio.invoices.seller' => []]);

    $order = createInvoiceReadyOrder();

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/invoice')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'seller.name',
            'seller.address_line_1',
            'seller.city',
            'seller.postal_code',
            'seller.country',
        ]);
});

it('returns 422 when the order has no billing address', function () {
    $order = createInvoiceReadyOrder([
        'addresses' => [],
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/invoice')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_address']);
});

it('returns 422 when the order has no lines', function () {
    $order = createInvoiceReadyOrder(withLine: false);

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/invoice')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order']);
});

it('returns 422 when the order lines use mixed currencies', function () {
    $order = createInvoiceReadyOrder();
    $secondCurrency = Currency::factory()->create([
        'code' => 'GBP',
        'name' => 'British Pound',
        'is_default' => false,
    ]);

    createOrderLine($order, $secondCurrency, [
        'product_name' => 'Second line',
        'product_sku' => 'TEST-SKU-002',
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/invoice')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency_code']);
});

function createInvoiceReadyOrder(array $overrides = [], bool $withLine = true): Order
{
    $currency = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'is_default' => false,
    ]);

    $order = Order::factory()->create(array_merge([
        'identifier' => fake()->unique()->regexify('ORD-[0-9]{6}'),
        'sub_total_taxable' => 20,
        'sub_total_tax' => 4.4,
        'sub_total' => 24.4,
        'shipping_fee' => 0,
        'payment_fee' => 0,
        'discount_amount' => 0,
        'total_final' => 24.4,
        'addresses' => [
            'billing' => [
                'first_name' => 'Invoice',
                'last_name' => 'Customer',
                'email' => 'invoice@example.test',
                'address_line_1' => 'Via Roma 1',
                'city' => 'Verona',
                'zip' => '37100',
                'country' => 'Italy',
            ],
            'shipping' => [
                'first_name' => 'Invoice',
                'last_name' => 'Customer',
                'address_line_1' => 'Via Roma 1',
                'city' => 'Verona',
                'zip' => '37100',
                'country' => 'Italy',
            ],
        ],
    ], $overrides));

    if ($withLine) {
        createOrderLine($order, $currency);
    }

    return $order->refresh();
}

function createOrderLine(Order $order, Currency $currency, array $overrides = []): void
{
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    config('venditio.models.order_line')::query()->create(array_merge([
        'order_id' => $order->getKey(),
        'product_id' => $product->getKey(),
        'currency_id' => $currency->getKey(),
        'product_name' => 'Test line item',
        'product_sku' => 'TEST-SKU-001',
        'discount_amount' => 0,
        'unit_price' => 20,
        'purchase_price' => 10,
        'unit_discount' => 0,
        'unit_final_price' => 20,
        'unit_final_price_tax' => 4.4,
        'unit_final_price_taxable' => 20,
        'qty' => 1,
        'total_final_price' => 24.4,
        'tax_rate' => 22,
        'product_data' => [
            'inventory' => [
                'price_includes_tax' => false,
            ],
        ],
    ], $overrides));
}

class TestInvoiceNumberGenerator implements InvoiceNumberGeneratorInterface
{
    public function generate(Illuminate\Database\Eloquent\Model $invoice): string
    {
        return 'INV-CUSTOM-001';
    }
}

class TestInvoicePayloadFactory implements InvoicePayloadFactoryInterface
{
    public function build(Illuminate\Database\Eloquent\Model $order): array
    {
        return [
            'currency_code' => 'USD',
            'seller' => [
                'name' => 'Custom Seller',
                'address_line_1' => 'Custom Street 1',
                'city' => 'Rome',
                'postal_code' => '00100',
                'country' => 'Italy',
            ],
            'billing_address' => [
                'first_name' => 'Custom',
                'last_name' => 'Buyer',
                'address_line_1' => 'Buyer Street 1',
                'city' => 'Naples',
                'zip' => '80100',
                'country' => 'Italy',
            ],
            'shipping_address' => [],
            'lines' => [
                [
                    'description' => 'Custom product',
                    'qty' => 1,
                    'unit_price' => 10,
                    'tax_rate' => 22,
                    'line_subtotal' => 10,
                    'line_total' => 12.2,
                ],
            ],
            'totals' => [
                'sub_total_taxable' => 10,
                'sub_total_tax' => 2.2,
                'sub_total' => 12.2,
                'shipping_fee' => 0,
                'payment_fee' => 0,
                'discount_amount' => 0,
                'total_final' => 12.2,
                'tax_breakdown' => [
                    [
                        'rate' => 22,
                        'taxable' => 10,
                        'amount' => 2.2,
                    ],
                ],
            ],
            'payments' => [
                [
                    'method' => 'Visa',
                    'paid_at' => now()->toDateString(),
                    'amount' => 12.2,
                    'reference' => 'PAY-1',
                ],
            ],
        ];
    }
}

class TestInvoiceTemplate implements InvoiceTemplateInterface
{
    public function key(): string
    {
        return 'custom-test';
    }

    public function version(): ?string
    {
        return '42';
    }

    public function render(array $invoice): string
    {
        return '<html><body>CUSTOM TEMPLATE ' . $invoice['identifier'] . ' ' . $invoice['order_identifier'] . '</body></html>';
    }
}

class TestInvoicePdfRenderer implements InvoicePdfRendererInterface
{
    public function render(string $html, array $options = []): string
    {
        return '%PDF-CUSTOM% ' . ($options['paper'] ?? 'unknown') . ' ' . $html;
    }
}

class TestInvoiceModelOverride extends Invoice
{
    protected $table = 'invoices';
}

class TestInvoicePolicy
{
    public function create(?Authenticatable $user): bool
    {
        return false;
    }

    public function view(?Authenticatable $user, TestInvoiceModelOverride $invoice): bool
    {
        return false;
    }
}
