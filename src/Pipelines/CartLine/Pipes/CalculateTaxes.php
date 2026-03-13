<?php

namespace PictaStudio\Venditio\Pipelines\CartLine\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use PictaStudio\Venditio\Actions\Taxes\{ExtractTaxFromGrossPrice, ResolveTaxRate};

use function PictaStudio\Venditio\Helpers\Functions\query;

class CalculateTaxes
{
    public function __construct(
        private readonly ExtractTaxFromGrossPrice $extractTaxFromGrossPrice,
        private readonly ResolveTaxRate $resolveTaxRate,
    ) {}

    public function __invoke(Model $cartLine, Closure $next): Model
    {
        $unitFinalPrice = (float) $cartLine->unit_final_price;

        $taxRate = $this->getTaxRate(
            $cartLine->getAttribute('product_data'),
            $this->getCartCountryId($cartLine)
        );
        $priceIncludesTax = $this->isPriceTaxInclusive($cartLine->getAttribute('product_data'));

        if ($priceIncludesTax) {
            $taxBreakdown = $this->extractTaxFromGrossPrice->handle($unitFinalPrice, $taxRate);
            $unitFinalPriceTaxable = $taxBreakdown['taxable'];
            $unitFinalPriceTax = $taxBreakdown['tax'];
        } else {
            $unitFinalPriceTaxable = $unitFinalPrice;
            $unitFinalPriceTax = round($unitFinalPrice * ($taxRate / 100), 2);
        }

        $cartLine->fill([
            'unit_final_price_tax' => $unitFinalPriceTax,
            'unit_final_price_taxable' => $unitFinalPriceTaxable,
            'tax_rate' => $taxRate,
        ]);

        return $next($cartLine);
    }

    private function getTaxRate(array $product, ?int $countryId): float
    {
        return $this->resolveTaxRate->handle(
            Arr::get($product, 'tax_class_id'),
            $countryId,
        );
    }

    private function isPriceTaxInclusive(array $product): bool
    {
        return (bool) Arr::get($product, 'inventory.price_includes_tax', true);
    }

    private function getCartCountryId(Model $cartLine): ?int
    {
        $cart = $cartLine->relationLoaded('cart') && $cartLine->cart instanceof Model
            ? $cartLine->cart
            : query('cart')->find($cartLine->getAttribute('cart_id'));

        if (!$cart instanceof Model) {
            return null;
        }

        $addresses = $cart->getAttribute('addresses');
        $billingCountryId = Arr::get($addresses, 'billing.country_id');
        $shippingCountryId = Arr::get($addresses, 'shipping.country_id');
        $countryId = $billingCountryId ?? $shippingCountryId;

        return is_numeric($countryId) ? (int) $countryId : null;
    }
}
