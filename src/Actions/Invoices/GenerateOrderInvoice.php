<?php

namespace PictaStudio\Venditio\Actions\Invoices;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PictaStudio\Venditio\Contracts\{InvoiceNumberGeneratorInterface, InvoicePayloadFactoryInterface, InvoiceTemplateInterface};
use PictaStudio\Venditio\Events\InvoiceCreated;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class GenerateOrderInvoice
{
    public function __construct(
        private readonly InvoiceNumberGeneratorInterface $numberGenerator,
        private readonly InvoicePayloadFactoryInterface $payloadFactory,
        private readonly InvoiceTemplateInterface $template,
    ) {}

    /**
     * @return array{invoice: Model, created: bool}
     */
    public function handle(Model $order): array
    {
        return DB::transaction(function () use ($order): array {
            $orderModel = resolve_model('order');
            $invoiceModel = resolve_model('invoice');

            /** @var Model $lockedOrder */
            $lockedOrder = $orderModel::query()
                ->with(['invoice', 'lines.currency'])
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            $existingInvoice = $lockedOrder->invoice()->first();

            if ($existingInvoice instanceof Model) {
                return [
                    'invoice' => $existingInvoice,
                    'created' => false,
                ];
            }

            $issuedAt = now();
            $payload = $this->payloadFactory->build($lockedOrder);

            /** @var Model $invoice */
            $invoice = new $invoiceModel;
            $invoice->fill([
                'order_id' => $lockedOrder->getKey(),
                'issued_at' => $issuedAt,
                'currency_code' => $payload['currency_code'],
                'template_key' => $this->template->key(),
                'template_version' => $this->template->version(),
                'locale' => config('venditio.invoices.locale') ?: app()->getLocale(),
                'paper' => config('venditio.invoices.paper', 'a4'),
                'orientation' => config('venditio.invoices.orientation', 'portrait'),
                'seller' => $payload['seller'],
                'billing_address' => $payload['billing_address'],
                'shipping_address' => $payload['shipping_address'],
                'lines' => $payload['lines'],
                'totals' => $payload['totals'],
                'payments' => $payload['payments'],
            ]);
            $invoice->identifier = $this->numberGenerator->generate($invoice);
            $invoice->rendered_html = $this->template->render([
                ...$payload,
                'identifier' => $invoice->identifier,
                'issued_at' => $issuedAt,
                'locale' => $invoice->locale,
                'order_identifier' => (string) $lockedOrder->identifier,
            ]);
            $invoice->save();

            $invoice->setRelation('order', $lockedOrder);

            event(new InvoiceCreated($invoice));

            return [
                'invoice' => $invoice,
                'created' => true,
            ];
        });
    }
}
