<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CreditNotePayloadFactoryInterface
{
    /**
     * Build an immutable credit note payload from the persisted order, invoice, and return request state.
     *
     * @return array<string, mixed>
     */
    public function build(Model $order, Model $invoice, Model $returnRequest): array;
}
