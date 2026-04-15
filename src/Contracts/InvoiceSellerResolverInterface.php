<?php

namespace PictaStudio\Venditio\Contracts;

interface InvoiceSellerResolverInterface
{
    /**
     * Resolve the seller payload used for immutable invoice snapshots.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array;
}
