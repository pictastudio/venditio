<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Gate, Route, Schema};
use PictaStudio\Venditio\Actions\Invoices\GenerateOrderInvoice;
use PictaStudio\Venditio\Contracts\{CreditNoteNumberGeneratorInterface, CreditNotePayloadFactoryInterface, CreditNotePdfRendererInterface, CreditNoteTemplateInterface};
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{CreditNote, Currency, Invoice, Order, Product, TaxClass};

use function Pest\Laravel\{deleteJson, get, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'venditio.credit_notes.enabled' => true,
        'venditio.credit_notes.number_generator' => PictaStudio\Venditio\Generators\CreditNoteNumberGenerator::class,
        'venditio.credit_notes.payload_factory' => PictaStudio\Venditio\CreditNotes\DefaultCreditNotePayloadFactory::class,
        'venditio.credit_notes.template' => PictaStudio\Venditio\CreditNotes\Templates\DefaultCreditNoteTemplate::class,
        'venditio.credit_notes.renderer' => PictaStudio\Venditio\CreditNotes\Renderers\DompdfCreditNotePdfRenderer::class,
        'venditio.credit_notes.locale' => 'it',
        'venditio.credit_notes.filename_pattern' => 'credit-note-{identifier}.pdf',
    ]);
});

it('registers credit note routes when credit notes are enabled', function () {
    $routePrefix = mb_rtrim((string) config('venditio.routes.api.v1.name'), '.');

    expect(Route::has($routePrefix . '.orders.credit_notes.index'))->toBeTrue()
        ->and(Route::has($routePrefix . '.orders.credit_notes.store'))->toBeTrue()
        ->and(Route::has($routePrefix . '.orders.credit_notes.show'))->toBeTrue()
        ->and(Route::has($routePrefix . '.orders.credit_notes.pdf'))->toBeTrue();
});

it('creates, lists, returns, and downloads a persisted credit note pdf', function () {
    [$order, $orderLine] = createCreditNoteReadyOrder();
    $invoice = ensureCreditOrderInvoice($order);
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);
    $prefix = config('venditio.routes.api.v1.prefix');

    $createResponse = postJson($prefix . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertCreated()
        ->assertJsonPath('order_id', $order->getKey())
        ->assertJsonPath('invoice_id', $invoice->getKey())
        ->assertJsonPath('return_request_id', $returnRequest->getKey())
        ->assertJsonPath('currency_code', 'USD')
        ->assertJsonPath('template_key', 'default')
        ->assertJsonPath('references.order_identifier', $order->identifier)
        ->assertJsonPath('references.invoice_identifier', $invoice->identifier)
        ->assertJsonPath('references.return_request_id', $returnRequest->getKey());

    $creditNoteId = $createResponse->json('id');
    $creditNote = CreditNote::query()->findOrFail($creditNoteId);

    expect($creditNote->rendered_html)
        ->toContain('Nota di credito')
        ->toContain((string) $creditNote->identifier)
        ->toContain((string) $invoice->identifier);

    $indexResponse = getJson($prefix . '/orders/' . $order->getKey() . '/credit_notes?all=1')
        ->assertOk();

    $indexedIds = collect(creditNoteApiListData($indexResponse->json()))
        ->pluck('id')
        ->all();

    expect($indexedIds)->toBe([$creditNoteId]);

    getJson($prefix . '/orders/' . $order->getKey() . '/credit_notes/' . $creditNoteId)
        ->assertOk()
        ->assertJsonPath('id', $creditNoteId)
        ->assertJsonPath('pdf_download_url', route(mb_rtrim((string) config('venditio.routes.api.v1.name'), '.') . '.orders.credit_notes.pdf', [
            'order' => $order->getKey(),
            'credit_note' => $creditNoteId,
        ]));

    $downloadResponse = get($prefix . '/orders/' . $order->getKey() . '/credit_notes/' . $creditNoteId . '/pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="credit-note-' . $creditNote->identifier . '.pdf"');

    expect($downloadResponse->getContent())->toStartWith('%PDF-');
});

it('copies the settings-backed invoice seller snapshot to credit notes', function () {
    createCreditNoteSellerSettingsTable();
    seedCreditNoteCompanySettings([
        'address' => 'Via Credit Settings 20',
        'city' => 'Treviso',
        'zip' => '31100',
        'province' => 'TV',
        'country' => 'Italy',
        'vat' => 'IT12312312312',
        'fiscal_code' => '12312312312',
        'email' => 'credit-settings@example.test',
        'pec' => 'credit-settings@pec.example.test',
        'sdi' => 'XYZ9876',
        'iban' => 'IT44A0306909606100000123456',
    ]);

    config()->set('venditio.invoices.seller', [
        'name' => 'Configured Credit Seller',
        'address_line_1' => 'Config Street 1',
        'city' => 'Config City',
        'postal_code' => '00000',
        'country' => 'Config Country',
    ]);

    [$order, $orderLine] = createCreditNoteReadyOrder();
    $invoice = ensureCreditOrderInvoice($order);
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);

    $creditNoteId = postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertCreated()
        ->assertJsonPath('seller.name', 'Configured Credit Seller')
        ->assertJsonPath('seller.address_line_1', 'Via Credit Settings 20')
        ->assertJsonPath('seller.city', 'Treviso')
        ->assertJsonPath('seller.postal_code', '31100')
        ->assertJsonPath('seller.state', 'TV')
        ->assertJsonPath('seller.country', 'Italy')
        ->assertJsonPath('seller.vat_number', 'IT12312312312')
        ->assertJsonPath('seller.tax_id', '12312312312')
        ->assertJsonPath('seller.email', 'credit-settings@example.test')
        ->assertJsonPath('seller.pec', 'credit-settings@pec.example.test')
        ->assertJsonPath('seller.sdi', 'XYZ9876')
        ->assertJsonPath('seller.iban', 'IT44A0306909606100000123456')
        ->json('id');

    $creditNote = CreditNote::query()->findOrFail($creditNoteId);

    expect($invoice->seller)->toMatchArray([
        'name' => 'Configured Credit Seller',
        'address_line_1' => 'Via Credit Settings 20',
        'city' => 'Treviso',
        'postal_code' => '31100',
        'state' => 'TV',
        'country' => 'Italy',
        'vat_number' => 'IT12312312312',
        'tax_id' => '12312312312',
        'email' => 'credit-settings@example.test',
        'pec' => 'credit-settings@pec.example.test',
        'sdi' => 'XYZ9876',
        'iban' => 'IT44A0306909606100000123456',
    ])->and($creditNote->seller)->toBe($invoice->seller);
});

it('returns the existing credit note on repeated creation requests', function () {
    [$order, $orderLine] = createCreditNoteReadyOrder();
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);
    ensureCreditOrderInvoice($order);
    $prefix = config('venditio.routes.api.v1.prefix');

    $firstCreditNoteId = postJson($prefix . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertCreated()->json('id');

    postJson($prefix . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertOk()
        ->assertJsonPath('id', $firstCreditNoteId);

    expect(CreditNote::query()->count())->toBe(1);
});

it('keeps credit note snapshots immutable after source records change', function () {
    [$order, $orderLine] = createCreditNoteReadyOrder();
    ensureCreditOrderInvoice($order);
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);
    $prefix = config('venditio.routes.api.v1.prefix');

    $creditNoteId = postJson($prefix . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertCreated()->json('id');

    $creditNoteBefore = CreditNote::query()->findOrFail($creditNoteId);
    $htmlBefore = $creditNoteBefore->rendered_html;

    $order->forceFill([
        'addresses' => [
            'billing' => [
                'first_name' => 'Changed',
                'last_name' => 'Customer',
                'address_line_1' => 'Changed Street 1',
                'city' => 'Milan',
                'zip' => '20100',
                'country' => 'Italy',
            ],
        ],
    ])->save();

    $orderLine->forceFill([
        'product_name' => 'Changed product',
        'unit_final_price_taxable' => 99,
        'unit_final_price_tax' => 21.78,
    ])->save();

    getJson($prefix . '/orders/' . $order->getKey() . '/credit_notes/' . $creditNoteId)
        ->assertOk()
        ->assertJsonPath('billing_address.city', 'Verona')
        ->assertJsonPath('lines.0.description', 'Test line item')
        ->assertJsonPath('lines.0.line_total', 24.4);

    $creditNoteAfter = CreditNote::query()->findOrFail($creditNoteId);

    expect($creditNoteAfter->rendered_html)->toBe($htmlBefore);
});

it('uses configured credit note generator, payload factory, template, and renderer overrides', function () {
    config([
        'venditio.credit_notes.number_generator' => TestCreditNoteNumberGenerator::class,
        'venditio.credit_notes.payload_factory' => TestCreditNotePayloadFactory::class,
        'venditio.credit_notes.template' => TestCreditNoteTemplate::class,
        'venditio.credit_notes.renderer' => TestCreditNotePdfRenderer::class,
        'venditio.credit_notes.paper' => 'letter',
    ]);

    app()->forgetInstance(CreditNoteNumberGeneratorInterface::class);
    app()->forgetInstance(CreditNotePayloadFactoryInterface::class);
    app()->forgetInstance(CreditNoteTemplateInterface::class);
    app()->forgetInstance(CreditNotePdfRendererInterface::class);

    [$order, $orderLine] = createCreditNoteReadyOrder();
    ensureCreditOrderInvoice($order);
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);
    $prefix = config('venditio.routes.api.v1.prefix');

    $creditNoteId = postJson($prefix . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertCreated()
        ->assertJsonPath('identifier', 'CN-CUSTOM-001')
        ->assertJsonPath('template_key', 'credit-custom-test')
        ->json('id');

    $creditNote = CreditNote::query()->findOrFail($creditNoteId);

    expect($creditNote->rendered_html)->toContain('CUSTOM CREDIT NOTE CN-CUSTOM-001');

    $downloadResponse = get($prefix . '/orders/' . $order->getKey() . '/credit_notes/' . $creditNoteId . '/pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($downloadResponse->getContent())
        ->toContain('%PDF-CUSTOM-CN%')
        ->toContain('letter');
});

it('honors credit note model policies for create and view endpoints', function () {
    config([
        'venditio.authorize_using_policies' => true,
        'venditio.models.credit_note' => TestCreditNoteModelOverride::class,
    ]);

    Gate::policy(TestCreditNoteModelOverride::class, TestCreditNotePolicy::class);

    [$order, $orderLine] = createCreditNoteReadyOrder();
    ensureCreditOrderInvoice($order);
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);
    $prefix = config('venditio.routes.api.v1.prefix');

    postJson($prefix . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertForbidden();

    config()->set('venditio.authorize_using_policies', false);
    $creditNoteId = postJson($prefix . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertCreated()->json('id');
    config()->set('venditio.authorize_using_policies', true);

    getJson($prefix . '/orders/' . $order->getKey() . '/credit_notes/' . $creditNoteId)
        ->assertForbidden();

    expect(TestCreditNoteModelOverride::query()->find($creditNoteId))->not->toBeNull();
});

it('returns 422 when the order does not have an invoice', function () {
    [$order, $orderLine] = createCreditNoteReadyOrder();
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['order_id']);
});

it('returns 422 when the return request belongs to another order', function () {
    [$firstOrder] = createCreditNoteReadyOrder();
    [$secondOrder, $secondOrderLine] = createCreditNoteReadyOrder();
    ensureCreditOrderInvoice($firstOrder);
    $foreignReturnRequest = createCreditNoteReturnRequest($secondOrder, [
        ['order_line' => $secondOrderLine, 'qty' => 1],
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $firstOrder->getKey() . '/credit_notes', [
        'return_request_id' => $foreignReturnRequest->getKey(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['return_request_id']);
});

it('returns 422 when the return request is not accepted', function () {
    [$order, $orderLine] = createCreditNoteReadyOrder();
    ensureCreditOrderInvoice($order);
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ], accepted: false);

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['return_request_id']);
});

it('returns 422 when the return request has no lines', function () {
    [$order] = createCreditNoteReadyOrder();
    ensureCreditOrderInvoice($order);
    $returnRequest = createCreditNoteReturnRequest($order, [], accepted: true);

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['return_request_id']);
});

it('returns 422 when the credited lines use mixed currencies', function () {
    [$order, $firstOrderLine] = createCreditNoteReadyOrder();
    ensureCreditOrderInvoice($order);

    $secondCurrency = Currency::factory()->create([
        'code' => 'GBP',
        'name' => 'British Pound',
        'is_default' => false,
    ]);

    $secondOrderLine = createCreditNoteOrderLine($order, $secondCurrency, [
        'product_name' => 'Second line',
        'product_sku' => 'TEST-SKU-002',
    ]);

    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $firstOrderLine, 'qty' => 1],
        ['order_line' => $secondOrderLine, 'qty' => 1],
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['currency_code']);
});

it('returns 404 when accessing a credit note through a different order', function () {
    [$firstOrder, $orderLine] = createCreditNoteReadyOrder();
    [$secondOrder] = createCreditNoteReadyOrder(withLine: false);
    ensureCreditOrderInvoice($firstOrder);
    $returnRequest = createCreditNoteReturnRequest($firstOrder, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);

    $creditNoteId = postJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $firstOrder->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertCreated()->json('id');

    getJson(config('venditio.routes.api.v1.prefix') . '/orders/' . $secondOrder->getKey() . '/credit_notes/' . $creditNoteId)
        ->assertNotFound();
});

it('prevents updating and deleting a credited return request', function () {
    [$order, $orderLine] = createCreditNoteReadyOrder();
    ensureCreditOrderInvoice($order);
    $returnRequest = createCreditNoteReturnRequest($order, [
        ['order_line' => $orderLine, 'qty' => 1],
    ]);
    $prefix = config('venditio.routes.api.v1.prefix');

    postJson($prefix . '/orders/' . $order->getKey() . '/credit_notes', [
        'return_request_id' => $returnRequest->getKey(),
    ])->assertCreated();

    patchJson($prefix . '/return_requests/' . $returnRequest->getKey(), [
        'notes' => 'Trying to change a credited return request',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['return_request_id']);

    deleteJson($prefix . '/return_requests/' . $returnRequest->getKey())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['return_request_id']);
});

function creditNoteApiListData(array $json): array
{
    return is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;
}

/**
 * @return array{0: Order, 1: Model|null}
 */
function createCreditNoteReadyOrder(array $overrides = [], bool $withLine = true): array
{
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        [
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'is_enabled' => true,
            'is_default' => false,
        ]
    );

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

    $orderLine = $withLine
        ? createCreditNoteOrderLine($order, $currency)
        : null;

    return [$order->refresh(), $orderLine];
}

function createCreditNoteOrderLine(Order $order, Currency $currency, array $overrides = []): Model
{
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    return config('venditio.models.order_line')::query()->create(array_merge([
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

function ensureCreditOrderInvoice(Order $order): Invoice
{
    /** @var Invoice $invoice */
    return app(GenerateOrderInvoice::class)->handle($order)['invoice'];
}

function createCreditNoteSellerSettingsTable(): void
{
    Schema::dropIfExists('settings');

    Schema::create('settings', function (Blueprint $table): void {
        $table->id();
        $table->string('group')->index();
        $table->string('name')->index();
        $table->text('value')->nullable();
        $table->timestamps();
        $table->unique(['group', 'name']);
    });
}

/**
 * @param  array<string, mixed>  $settings
 */
function seedCreditNoteCompanySettings(array $settings): void
{
    $timestamp = now();

    DB::table('settings')->insert(
        collect($settings)
            ->map(fn (mixed $value, string $name): array => [
                'group' => 'company',
                'name' => $name,
                'value' => $value,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->values()
            ->all()
    );
}

function createCreditNoteReturnRequest(Order $order, array $lines, bool $accepted = true): Model
{
    $returnReason = config('venditio.models.return_reason')::query()->create([
        'code' => 'reason-' . uniqid(),
        'name' => 'Return reason',
        'description' => 'Return reason description',
        'active' => true,
        'sort_order' => 0,
    ]);

    $billingAddress = data_get($order->addresses?->toArray() ?? [], 'billing', []);

    $returnRequest = config('venditio.models.return_request')::query()->create([
        'order_id' => $order->getKey(),
        'user_id' => $order->user_id,
        'return_reason_id' => $returnReason->getKey(),
        'billing_address' => is_array($billingAddress) ? $billingAddress : [],
        'description' => 'Credit note return request',
        'notes' => 'Credit note notes',
        'is_accepted' => $accepted,
        'is_verified' => false,
    ]);

    foreach ($lines as $line) {
        config('venditio.models.return_request_line')::query()->create([
            'return_request_id' => $returnRequest->getKey(),
            'order_line_id' => $line['order_line']->getKey(),
            'qty' => $line['qty'],
        ]);
    }

    return $returnRequest->refresh();
}

class TestCreditNoteNumberGenerator implements CreditNoteNumberGeneratorInterface
{
    public function generate(Model $creditNote): string
    {
        return 'CN-CUSTOM-001';
    }
}

class TestCreditNotePayloadFactory implements CreditNotePayloadFactoryInterface
{
    public function build(Model $order, Model $invoice, Model $returnRequest): array
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
            'references' => [
                'order_identifier' => (string) $order->identifier,
                'invoice_identifier' => (string) $invoice->identifier,
                'return_request_id' => (int) $returnRequest->getKey(),
            ],
            'lines' => [
                [
                    'description' => 'Custom credited product',
                    'qty' => 1,
                    'unit_price' => 10,
                    'unit_tax' => 2.2,
                    'tax_rate' => 22,
                    'line_subtotal' => 10,
                    'line_tax' => 2.2,
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
        ];
    }
}

class TestCreditNoteTemplate implements CreditNoteTemplateInterface
{
    public function key(): string
    {
        return 'credit-custom-test';
    }

    public function version(): ?string
    {
        return '7';
    }

    public function render(array $creditNote): string
    {
        return '<html><body>CUSTOM CREDIT NOTE ' . $creditNote['identifier'] . ' ' . $creditNote['references']['order_identifier'] . '</body></html>';
    }
}

class TestCreditNotePdfRenderer implements CreditNotePdfRendererInterface
{
    public function render(string $html, array $options = []): string
    {
        return '%PDF-CUSTOM-CN% ' . ($options['paper'] ?? 'unknown') . ' ' . $html;
    }
}

class TestCreditNoteModelOverride extends CreditNote
{
    protected $table = 'credit_notes';
}

class TestCreditNotePolicy
{
    public function create(?Authenticatable $user): bool
    {
        return false;
    }

    public function view(?Authenticatable $user, TestCreditNoteModelOverride $creditNote): bool
    {
        return false;
    }
}
