<?php

namespace PictaStudio\Venditio\Dto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Collection, Fluent};
use PictaStudio\Venditio\Dto\Contracts\OrderDtoContract;
use ReflectionClass;

use function PictaStudio\Venditio\Helpers\Functions\get_fresh_model_instance;

class OrderDto extends Dto implements OrderDtoContract
{
    public function __construct(
        private Model $order,
        private ?Model $cart,
        private ?int $userId,
        private ?string $userFirstName,
        private ?string $userLastName,
        private ?string $userEmail,
        private ?array $addresses,
        private ?string $customerNotes,
        private array $lines, // to swap with an order line dto
        private ?int $shippingMethodId = null,
        private ?int $shippingZoneId = null,

        // TODO: add the rest of the properties
        private ?float $subTotalTaxable = null,
        private ?float $subTotalTax = null,
        private ?float $subTotal = null,
        private ?float $shippingFee = null,
        private ?float $specificWeight = null,
        private ?float $volumetricWeight = null,
        private ?float $chargeableWeight = null,
        private ?float $paymentFee = null,
        private ?string $discountCode = null,
        private ?float $discountAmount = null,
        private ?float $totalFinal = null,
        private ?array $shippingMethodData = null,
        private ?array $shippingZoneData = null,
    ) {}

    public static function fromCart(Model $cart): static
    {
        $cart->loadMissing(['shippingMethod', 'shippingZone']);
        $addresses = $cart->addresses;

        /** @var static $dto */
        $dto = (new ReflectionClass(static::class))->newInstanceArgs([
            static::getFreshInstance(),
            $cart,
            $cart->user_id,
            $cart->user_first_name,
            $cart->user_last_name,
            $cart->user_email,
            $addresses instanceof Fluent ? $addresses->toArray() : $addresses,
            $cart->notes,
            $cart->lines->toArray(),
            $cart->shipping_method_id,
            $cart->shipping_zone_id,
            $cart->sub_total_taxable,
            $cart->sub_total_tax,
            $cart->sub_total,
            $cart->shipping_fee,
            $cart->specific_weight,
            $cart->volumetric_weight,
            $cart->chargeable_weight,
            $cart->payment_fee,
            $cart->discount_code,
            $cart->discount_amount,
            $cart->total_final,
            $cart->shippingMethod?->toArray(),
            $cart->shippingZone?->toArray(),
        ]);

        return $dto;
    }

    public static function fromArray(array $data): static
    {
        /** @var static $dto */
        $dto = (new ReflectionClass(static::class))->newInstanceArgs([
            $data['order'] ?? static::getFreshInstance(),
            $data['cart'] ?? null,
            $data['user_id'] ?? null,
            $data['user_first_name'] ?? null,
            $data['user_last_name'] ?? null,
            $data['user_email'] ?? null,
            $data['addresses'] ?? null,
            $data['notes'] ?? null,
            $data['lines'] ?? [],
            $data['shipping_method_id'] ?? null,
            $data['shipping_zone_id'] ?? null,
            $data['sub_total_taxable'] ?? null,
            $data['sub_total_tax'] ?? null,
            $data['sub_total'] ?? null,
            $data['shipping_fee'] ?? null,
            $data['specific_weight'] ?? null,
            $data['volumetric_weight'] ?? null,
            $data['chargeable_weight'] ?? null,
            $data['payment_fee'] ?? null,
            $data['discount_code'] ?? null,
            $data['discount_amount'] ?? null,
            $data['total_final'] ?? null,
            $data['shipping_method_data'] ?? null,
            $data['shipping_zone_data'] ?? null,
        ]);

        return $dto;
    }

    public static function getFreshInstance(): Model
    {
        return get_fresh_model_instance('order');
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'user_first_name' => $this->getUserFirstName(),
            'user_last_name' => $this->getUserLastName(),
            'user_email' => $this->getUserEmail(),
            'shipping_method_id' => $this->shippingMethodId ?? $this->cart?->shipping_method_id,
            'shipping_zone_id' => $this->shippingZoneId ?? $this->cart?->shipping_zone_id,
            'sub_total_taxable' => $this->subTotalTaxable ?? $this->cart?->sub_total_taxable ?? 0,
            'sub_total_tax' => $this->subTotalTax ?? $this->cart?->sub_total_tax ?? 0,
            'sub_total' => $this->subTotal ?? $this->cart?->sub_total ?? 0,
            'shipping_fee' => $this->shippingFee ?? $this->cart?->shipping_fee ?? 0,
            'specific_weight' => $this->specificWeight ?? $this->cart?->specific_weight ?? 0,
            'volumetric_weight' => $this->volumetricWeight ?? $this->cart?->volumetric_weight ?? 0,
            'chargeable_weight' => $this->chargeableWeight ?? $this->cart?->chargeable_weight ?? 0,
            'payment_fee' => $this->paymentFee ?? $this->cart?->payment_fee ?? 0,
            'discount_code' => $this->getDiscountCode(),
            'discount_amount' => $this->discountAmount ?? $this->cart?->discount_amount ?? 0,
            'total_final' => $this->totalFinal ?? $this->cart?->total_final ?? 0,
            'addresses' => $this->addresses
                ?? $this->normalizeAddresses($this->cart?->addresses)
                ?? [],
            'customer_notes' => $this->getCustomerNotes(),
            'shipping_method_data' => $this->shippingMethodData ?? $this->cart?->shippingMethod?->toArray(),
            'shipping_zone_data' => $this->shippingZoneData ?? $this->cart?->shippingZone?->toArray(),
        ];
    }

    public function toModel(): Model
    {
        return $this->getFreshInstance()
            ->fill($this->toArray());
    }

    public function getModel(): Model
    {
        return $this->order;
    }

    public function getCart(): Model
    {
        return $this->cart ?? get_fresh_model_instance('cart');
    }

    public function getUserId(): ?int
    {
        return $this->userId ?? auth()->guard()->id();
    }

    public function getUserFirstName(): ?string
    {
        return $this->userFirstName ?? $this->getModel()?->user_first_name;
    }

    public function getUserLastName(): ?string
    {
        return $this->userLastName ?? $this->getModel()?->user_last_name;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail ?? $this->getModel()?->user_email;
    }

    public function getDiscountCode(): ?string
    {
        return $this->discountCode ?? $this->getModel()?->discount_code;
    }

    public function getBillingAddress(): ?array
    {
        return $this->addresses['billing'] ?? $this->getModel()?->addresses['billing'] ?? null;
    }

    public function getShippingAddress(): ?array
    {
        return $this->addresses['shipping'] ?? $this->getModel()?->addresses['shipping'] ?? null;
    }

    public function getCustomerNotes(): ?string
    {
        return $this->customerNotes ?? $this->getModel()?->customer_notes ?? null;
    }

    /**
     * @return Collection<[['product_id' => int, 'qty' => int]]>
     */
    public function getLines(): Collection
    {
        return collect($this->lines);
    }

    private function normalizeAddresses(mixed $addresses): ?array
    {
        if (is_array($addresses)) {
            return $addresses;
        }

        if ($addresses instanceof Fluent) {
            return $addresses->toArray();
        }

        return null;
    }
}
