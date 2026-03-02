<?php

namespace PictaStudio\Venditio\Actions\ProductVariants;

use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\ProductVariant;
use PictaStudio\Venditio\Models\Scopes\Active;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateProductVariant
{
    public function handle(array $payload): ProductVariant
    {
        if (blank($payload['product_type_id'] ?? null)) {
            $defaultProductType = resolve_model('product_type')::withoutGlobalScope(Active::class)
                ->where('is_default', true)
                ->first();

            if ($defaultProductType) {
                $payload['product_type_id'] = $defaultProductType->getKey();
            }
        }

        if (blank($payload['product_type_id'] ?? null)) {
            throw ValidationException::withMessages([
                'product_type_id' => ['product_type_id is required when no default product type is configured.'],
            ]);
        }

        /** @var ProductVariant $variant */
        $variant = resolve_model('product_variant')::create($payload);

        return $variant->refresh();
    }
}
