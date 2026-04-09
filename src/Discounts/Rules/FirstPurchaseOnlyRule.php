<?php

namespace PictaStudio\Venditio\Discounts\Rules;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Contracts\DiscountRuleInterface;
use PictaStudio\Venditio\Discounts\DiscountContext;
use PictaStudio\Venditio\Models\Discount;

use function PictaStudio\Venditio\Helpers\Functions\query;

class FirstPurchaseOnlyRule implements DiscountRuleInterface
{
    public function passes(Discount $discount, Model $line, DiscountContext $context): bool
    {
        if (!$discount->first_purchase_only) {
            return true;
        }

        $user = $context->getUser();

        if (!$user instanceof Model || blank($user->getKey())) {
            return false;
        }

        $currentOrder = $context->getOrder();
        $currentOrderKey = $currentOrder instanceof Model && filled($currentOrder->getKey())
            ? $currentOrder->getKey()
            : null;
        $completedStatus = config('venditio.order.status_enum')::getCompletedStatus();
        $completedStatusValue = is_object($completedStatus) && isset($completedStatus->value)
            ? $completedStatus->value
            : $completedStatus;

        return !query('order')
            ->where('user_id', $user->getKey())
            ->when(
                filled($currentOrderKey),
                fn ($query) => $query->whereKeyNot($currentOrderKey)
            )
            ->where(function ($query) use ($completedStatusValue) {
                $query->whereNotNull('approved_at')
                    ->orWhere('status', $completedStatusValue);
            })
            ->exists();
    }
}
