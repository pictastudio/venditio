<?php

namespace PictaStudio\Venditio\Pipelines\Order\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Arr, Collection};
use PictaStudio\Venditio\Actions\Taxes\{ExtractTaxFromGrossPrice, ResolveTaxRate};
use PictaStudio\Venditio\Dto\Contracts\OrderDtoContract;
use PictaStudio\Venditio\Models\Cart;

use function PictaStudio\Venditio\Helpers\Functions\get_fresh_model_instance;

class FillOrderFromCart
{
    public function __construct(
        private readonly ExtractTaxFromGrossPrice $extractTaxFromGrossPrice,
        private readonly ResolveTaxRate $resolveTaxRate,
    ) {}

    public function __invoke(OrderDtoContract $orderDto, Closure $next): Model
    {
        $cart = $orderDto->getCart()->loadMissing('lines');
        $order = $orderDto->toModel();
        $order->fill([
            'status' => config('venditio.order.status_enum')::getProcessingStatus(),
        ]);

        $order->setRelation('sourceCart', $cart);
        $order->setRelation('lines', $this->mapCartLineToOrderLine($cart));

        return $next($order);
    }

    public function mapCartLineToOrderLine(Cart|Model $cart): Collection
    {
        $billingCountryId = $this->resolveBillingCountryId($cart);

        return $cart->lines->map(function (Model $cartLine) use ($billingCountryId) {
            $orderLine = get_fresh_model_instance('order_line');
            $productData = $cartLine->getAttribute('product_data') ?? [];
            $unitFinalPrice = (float) $cartLine->unit_final_price;
            $taxRate = $this->resolveTaxRate->handle(
                Arr::get($productData, 'tax_class_id'),
                $billingCountryId,
            );
            $priceIncludesTax = (bool) data_get($productData, 'inventory.price_includes_tax', true);
            $taxBreakdown = $this->resolveTaxBreakdown($unitFinalPrice, $taxRate, $priceIncludesTax);

            return $orderLine->fill([
                'product_id' => $cartLine->product_id,
                'currency_id' => $cartLine->currency_id,
                'free_gift_id' => $cartLine->free_gift_id,
                'is_free_gift' => (bool) $cartLine->is_free_gift,
                'free_gift_data' => $cartLine->free_gift_data,
                'discount_id' => $cartLine->discount_id,
                'discount_code' => $cartLine->discount_code,
                'discount_amount' => $cartLine->discount_amount,
                'product_name' => $cartLine->product_name,
                'product_sku' => $cartLine->product_sku,
                'unit_price' => $cartLine->unit_price,
                'purchase_price' => $cartLine->purchase_price,
                'unit_discount' => $cartLine->unit_discount,
                'unit_final_price' => $cartLine->unit_final_price,
                'unit_final_price_tax' => $taxBreakdown['tax'],
                'unit_final_price_taxable' => $taxBreakdown['taxable'],
                'qty' => $cartLine->qty,
                'total_final_price' => ($taxBreakdown['taxable'] + $taxBreakdown['tax']) * $cartLine->qty,
                'tax_rate' => $taxRate,
                'product_data' => $productData,
            ]);
        });
    }

    private function resolveBillingCountryId(Cart|Model $cart): ?int
    {
        $addresses = $cart->getAttribute('addresses');
        $countryId = Arr::get($addresses, 'billing.country_id');

        return is_numeric($countryId) ? (int) $countryId : null;
    }

    /**
     * @return array{taxable: float, tax: float}
     */
    private function resolveTaxBreakdown(float $unitFinalPrice, float $taxRate, bool $priceIncludesTax): array
    {
        if ($priceIncludesTax) {
            return $this->extractTaxFromGrossPrice->handle($unitFinalPrice, $taxRate);
        }

        return [
            'taxable' => $unitFinalPrice,
            'tax' => round($unitFinalPrice * ($taxRate / 100), 2),
        ];
    }
}
