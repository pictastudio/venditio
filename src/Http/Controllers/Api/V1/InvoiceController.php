<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{JsonResponse, Response};
use PictaStudio\Venditio\Actions\Invoices\GenerateOrderInvoice;
use PictaStudio\Venditio\Contracts\InvoicePdfRendererInterface;
use PictaStudio\Venditio\Events\InvoicePdfRendered;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Resources\V1\InvoiceResource;
use PictaStudio\Venditio\Models\Order;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class InvoiceController extends Controller
{
    public function store(Order $order, GenerateOrderInvoice $generateOrderInvoice): JsonResponse
    {
        $existingInvoice = $order->invoice()->first();

        if ($existingInvoice instanceof Model) {
            $this->authorizeIfConfigured('view', $existingInvoice);

            return InvoiceResource::make($existingInvoice)
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        }

        $this->authorizeIfConfigured('create', resolve_model('invoice'));

        $result = $generateOrderInvoice->handle($order);
        $invoice = $result['invoice'];
        $status = $result['created']
            ? Response::HTTP_CREATED
            : Response::HTTP_OK;

        if (!$result['created']) {
            $this->authorizeIfConfigured('view', $invoice);
        }

        return InvoiceResource::make($invoice)
            ->response()
            ->setStatusCode($status);
    }

    public function show(Order $order): InvoiceResource
    {
        $invoice = $this->resolveOrderInvoice($order);

        $this->authorizeIfConfigured('view', $invoice);

        return InvoiceResource::make($invoice);
    }

    public function pdf(Order $order, InvoicePdfRendererInterface $renderer): Response
    {
        $invoice = $this->resolveOrderInvoice($order);

        $this->authorizeIfConfigured('view', $invoice);

        $binary = $renderer->render($invoice->rendered_html, [
            'paper' => $invoice->paper,
            'orientation' => $invoice->orientation,
        ]);

        event(new InvoicePdfRendered($invoice));

        return response($binary, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->resolveFilename($invoice) . '"',
        ]);
    }

    protected function resolveOrderInvoice(Order $order): Model
    {
        $invoice = $order->invoice()->first();

        abort_if(!$invoice instanceof Model, Response::HTTP_NOT_FOUND);

        return $invoice;
    }

    protected function resolveFilename(Model $invoice): string
    {
        $order = $invoice->relationLoaded('order')
            ? $invoice->order
            : $invoice->order()->first();
        $pattern = (string) config('venditio.invoices.filename_pattern', 'invoice-{identifier}.pdf');

        $filename = str($pattern)
            ->swap([
                '{id}' => (string) $invoice->getKey(),
                '{identifier}' => (string) $invoice->identifier,
                '{order_id}' => (string) $invoice->order_id,
                '{order_identifier}' => (string) ($order?->identifier ?? ''),
                '{date}' => $invoice->issued_at?->format('Ymd') ?? now()->format('Ymd'),
            ])
            ->toString();

        if (!str($filename)->lower()->endsWith('.pdf')) {
            $filename .= '.pdf';
        }

        return $filename;
    }
}
