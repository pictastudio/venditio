<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;

interface InvoicePayloadFactoryInterface
{
    /**
     * Build an immutable invoice payload from the persisted order state.
     *
     * @return array<string, mixed>
     */
    public function build(Model $order): array;
}
