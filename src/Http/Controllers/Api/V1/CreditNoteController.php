<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{JsonResponse, Response};
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Actions\CreditNotes\GenerateOrderCreditNote;
use PictaStudio\Venditio\Contracts\CreditNotePdfRendererInterface;
use PictaStudio\Venditio\Events\CreditNotePdfRendered;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\CreditNote\StoreCreditNoteRequest;
use PictaStudio\Venditio\Http\Resources\V1\CreditNoteResource;
use PictaStudio\Venditio\Models\Order;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class CreditNoteController extends Controller
{
    public function index(Order $order): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('credit_note'));

        return CreditNoteResource::collection(
            $this->applyBaseFilters(
                query('credit_note')->where('order_id', $order->getKey()),
                request()->all(),
                'credit_note'
            )
        );
    }

    public function store(Order $order, StoreCreditNoteRequest $request, GenerateOrderCreditNote $generateOrderCreditNote): JsonResponse
    {
        $existingCreditNote = query('credit_note')
            ->where('return_request_id', (int) $request->validated('return_request_id'))
            ->where('order_id', $order->getKey())
            ->first();

        if ($existingCreditNote instanceof Model) {
            $this->authorizeIfConfigured('view', $existingCreditNote);

            return CreditNoteResource::make($existingCreditNote)
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        }

        $this->authorizeIfConfigured('create', resolve_model('credit_note'));

        $result = $generateOrderCreditNote->handle(
            $order,
            (int) $request->validated('return_request_id')
        );
        $creditNote = $result['credit_note'];
        $status = $result['created']
            ? Response::HTTP_CREATED
            : Response::HTTP_OK;

        if (!$result['created']) {
            $this->authorizeIfConfigured('view', $creditNote);
        }

        return CreditNoteResource::make($creditNote)
            ->response()
            ->setStatusCode($status);
    }

    public function show(Order $order, Model $credit_note): CreditNoteResource
    {
        $creditNote = $this->resolveOrderCreditNote($order, $credit_note);

        $this->authorizeIfConfigured('view', $creditNote);

        return CreditNoteResource::make($creditNote);
    }

    public function pdf(Order $order, Model $credit_note, CreditNotePdfRendererInterface $renderer): Response
    {
        $creditNote = $this->resolveOrderCreditNote($order, $credit_note);

        $this->authorizeIfConfigured('view', $creditNote);

        $binary = $renderer->render($creditNote->rendered_html, [
            'paper' => $creditNote->paper,
            'orientation' => $creditNote->orientation,
        ]);

        event(new CreditNotePdfRendered($creditNote));

        return response($binary, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->resolveFilename($creditNote) . '"',
        ]);
    }

    protected function resolveOrderCreditNote(Order $order, Model $creditNote): Model
    {
        abort_if((int) $creditNote->order_id !== (int) $order->getKey(), Response::HTTP_NOT_FOUND);

        return $creditNote;
    }

    protected function resolveFilename(Model $creditNote): string
    {
        $order = $creditNote->relationLoaded('order')
            ? $creditNote->order
            : $creditNote->order()->first();
        $invoice = $creditNote->relationLoaded('invoice')
            ? $creditNote->invoice
            : $creditNote->invoice()->first();
        $pattern = (string) config('venditio.credit_notes.filename_pattern', 'credit-note-{identifier}.pdf');

        $filename = str($pattern)
            ->swap([
                '{id}' => (string) $creditNote->getKey(),
                '{identifier}' => (string) $creditNote->identifier,
                '{order_id}' => (string) $creditNote->order_id,
                '{order_identifier}' => (string) ($order?->identifier ?? ''),
                '{invoice_id}' => (string) $creditNote->invoice_id,
                '{invoice_identifier}' => (string) ($invoice?->identifier ?? ''),
                '{return_request_id}' => (string) $creditNote->return_request_id,
                '{date}' => $creditNote->issued_at?->format('Ymd') ?? now()->format('Ymd'),
            ])
            ->toString();

        if (!str($filename)->lower()->endsWith('.pdf')) {
            $filename .= '.pdf';
        }

        return $filename;
    }
}
