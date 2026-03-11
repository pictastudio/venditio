<?php

namespace PictaStudio\Venditio\Pipelines\Cart\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Dto\Contracts\CartLineDtoContract;
use PictaStudio\Venditio\Models\CartLine;
use PictaStudio\Venditio\Pipelines\CartLine\CartLineUpdatePipeline;

use function PictaStudio\Venditio\Helpers\Functions\resolve_dto;

class UpdateLines
{
    public function __invoke(Model $cart, Closure $next): Model
    {
        $linesPayloadProvided = (bool) $cart->getAttribute('lines_payload_provided');
        $cart->offsetUnset('lines_payload_provided');
        $incomingLines = $linesPayloadProvided ? $cart->getRelation('lines') : null;
        $existingLines = $cart->load('lines')->getRelation('lines');

        if ($linesPayloadProvided && $incomingLines instanceof Collection) {
            $existingByProductId = $existingLines->keyBy('product_id');
            $updatedLines = $incomingLines->map(function (mixed $linePayload) use ($cart, $existingByProductId) {
                $lineData = $linePayload instanceof CartLineDtoContract
                    ? [
                        'product_id' => $linePayload->getPurchasableModelId(),
                        'qty' => $linePayload->getQty(),
                    ]
                    : (array) $linePayload;

                $productId = (int) ($lineData['product_id'] ?? 0);
                $qty = (int) ($lineData['qty'] ?? 0);

                /** @var CartLine $line */
                $line = $existingByProductId->get($productId) ?? resolve_dto('cart_line')::getFreshInstance();
                $line->cart()->associate($cart);

                return CartLineUpdatePipeline::make()->run(
                    resolve_dto('cart_line')::fromArray([
                        'cart' => $cart,
                        'cart_line' => $line,
                        'product_id' => $productId,
                        'qty' => $qty,
                    ])
                );
            });

            $incomingProductIds = $updatedLines
                ->pluck('product_id')
                ->map(fn (mixed $id) => (int) $id)
                ->all();

            $linesToDelete = $existingLines
                ->filter(fn (Model $line) => !in_array((int) $line->product_id, $incomingProductIds, true))
                ->pluck('id')
                ->all();

            $cart->setAttribute('lines_to_delete', $linesToDelete);
            $cart->setRelation('lines', $updatedLines);

            return $next($cart);
        }

        $updatedLines = $existingLines->map(function (CartLine $line) use ($cart) {
            $line->setRelation('cart', $cart);
            $line->cart()->associate($cart);

            return CartLineUpdatePipeline::make()->run(
                resolve_dto('cart_line')::fromArray([
                    'cart' => $cart,
                    'cart_line' => $line,
                    'product_id' => $line['product_id'] ?? null,
                    'qty' => $line['qty'],
                ])
            );
        });

        $cart->setAttribute('lines_to_delete', []);
        $cart->setRelation('lines', $updatedLines);

        return $next($cart);
    }
}
