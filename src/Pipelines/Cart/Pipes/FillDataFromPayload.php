<?php

namespace PictaStudio\Venditio\Pipelines\Cart\Pipes;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Dto\Contracts\CartDtoContract;

use function PictaStudio\Venditio\Helpers\Functions\resolve_enum;

class FillDataFromPayload
{
    public function __invoke(CartDtoContract $cartDto, Closure $next): Model
    {
        $addresses = $cartDto->getAddresses();

        if (blank($addresses)) {
            /** @var BackedEnum $addressTypeEnum */
            $addressTypeEnum = config('venditio.addresses.type_enum');

            foreach ($addressTypeEnum::cases() as $case) {
                $addresses[$case->value] = [];
            }
        }

        $cart = $cartDto->toModel();

        $cart->fill([
            'status' => resolve_enum('cart_status')::getProcessingStatus(),
            'shipping_rate_id' => $cartDto->getShippingRateId(),
            'addresses' => $addresses,
        ]);

        if (!$cart->exists || $cartDto->hasLinesProvided()) {
            $cart->setRelation('lines', $cartDto->getLines());
        }

        $cart->setAttribute('lines_payload_provided', $cartDto->hasLinesProvided());

        return $next($cart);
    }
}
