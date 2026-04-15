<?php

namespace PictaStudio\Venditio\Actions\CreditNotes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\{CreditNoteNumberGeneratorInterface, CreditNotePayloadFactoryInterface, CreditNoteTemplateInterface};
use PictaStudio\Venditio\Events\CreditNoteCreated;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class GenerateOrderCreditNote
{
    public function __construct(
        private readonly CreditNoteNumberGeneratorInterface $numberGenerator,
        private readonly CreditNotePayloadFactoryInterface $payloadFactory,
        private readonly CreditNoteTemplateInterface $template,
    ) {}

    /**
     * @return array{credit_note: Model, created: bool}
     */
    public function handle(Model $order, int $returnRequestId): array
    {
        return DB::transaction(function () use ($order, $returnRequestId): array {
            $orderModel = resolve_model('order');
            $invoiceModel = resolve_model('invoice');
            $returnRequestModel = resolve_model('return_request');
            $creditNoteModel = resolve_model('credit_note');

            /** @var Model $lockedOrder */
            $lockedOrder = $orderModel::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            $invoice = $invoiceModel::query()
                ->lockForUpdate()
                ->where('order_id', $lockedOrder->getKey())
                ->first();

            if (!$invoice instanceof Model) {
                throw ValidationException::withMessages([
                    'order_id' => ['An invoice must exist before a credit note can be generated.'],
                ]);
            }

            /** @var Model $returnRequest */
            $returnRequest = $returnRequestModel::query()
                ->lockForUpdate()
                ->findOrFail($returnRequestId);

            if ((int) $returnRequest->order_id !== (int) $lockedOrder->getKey()) {
                throw ValidationException::withMessages([
                    'return_request_id' => ['The selected return request does not belong to the selected order.'],
                ]);
            }

            $existingCreditNote = $creditNoteModel::query()
                ->lockForUpdate()
                ->where('return_request_id', $returnRequest->getKey())
                ->first();

            if ($existingCreditNote instanceof Model) {
                return [
                    'credit_note' => $existingCreditNote,
                    'created' => false,
                ];
            }

            if (!(bool) $returnRequest->is_accepted) {
                throw ValidationException::withMessages([
                    'return_request_id' => ['Only accepted return requests can be credited.'],
                ]);
            }

            $returnLines = $returnRequest->lines()
                ->lockForUpdate()
                ->with(['orderLine.currency'])
                ->get();

            $returnRequest->setRelation('lines', $returnLines);

            $payload = $this->payloadFactory->build($lockedOrder, $invoice, $returnRequest);
            $issuedAt = now();

            /** @var Model $creditNote */
            $creditNote = new $creditNoteModel;
            $creditNote->fill([
                'order_id' => $lockedOrder->getKey(),
                'invoice_id' => $invoice->getKey(),
                'return_request_id' => $returnRequest->getKey(),
                'issued_at' => $issuedAt,
                'currency_code' => $payload['currency_code'],
                'template_key' => $this->template->key(),
                'template_version' => $this->template->version(),
                'locale' => config('venditio.credit_notes.locale') ?: app()->getLocale(),
                'paper' => config('venditio.credit_notes.paper', 'a4'),
                'orientation' => config('venditio.credit_notes.orientation', 'portrait'),
                'seller' => $payload['seller'],
                'billing_address' => $payload['billing_address'],
                'shipping_address' => $payload['shipping_address'],
                'references' => $payload['references'],
                'lines' => $payload['lines'],
                'totals' => $payload['totals'],
            ]);
            $creditNote->identifier = $this->numberGenerator->generate($creditNote);
            $creditNote->rendered_html = $this->template->render([
                ...$payload,
                'identifier' => $creditNote->identifier,
                'issued_at' => $issuedAt,
                'locale' => $creditNote->locale,
            ]);
            $creditNote->save();

            $creditNote->setRelation('order', $lockedOrder);
            $creditNote->setRelation('invoice', $invoice);
            $creditNote->setRelation('returnRequest', $returnRequest);

            event(new CreditNoteCreated($creditNote));

            return [
                'credit_note' => $creditNote,
                'created' => true,
            ];
        });
    }
}
