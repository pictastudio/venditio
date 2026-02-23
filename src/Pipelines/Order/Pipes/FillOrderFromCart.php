<?php

namespace PictaStudio\Venditio\Pipelines\Order\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Dto\Contracts\OrderDtoContract;
use PictaStudio\Venditio\Models\Cart;

use function PictaStudio\Venditio\Helpers\Functions\get_fresh_model_instance;

class FillOrderFromCart
{
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
        return $cart->lines->map(function (Model $cartLine) {
            $orderLine = get_fresh_model_instance('order_line');

            return $orderLine->fill([
                'product_id' => $cartLine->product_id,
                'currency_id' => $cartLine->currency_id,
                'discount_id' => $cartLine->discount_id,
                'discount_code' => $cartLine->discount_code,
                'discount_amount' => $cartLine->discount_amount,
                'product_name' => $cartLine->product_name,
                'product_sku' => $cartLine->product_sku,
                'unit_price' => $cartLine->unit_price,
                'purchase_price' => $cartLine->purchase_price,
                'unit_discount' => $cartLine->unit_discount,
                'unit_final_price' => $cartLine->unit_final_price,
                'unit_final_price_tax' => $cartLine->unit_final_price_tax,
                'unit_final_price_taxable' => $cartLine->unit_final_price_taxable,
                'qty' => $cartLine->qty,
                'total_final_price' => $cartLine->total_final_price,
                'tax_rate' => $cartLine->tax_rate,
                'product_data' => $cartLine->getAttribute('product_data'),
            ]);
        });
    }
}
