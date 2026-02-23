<?php

namespace PictaStudio\Venditio\Dto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Collection, Fluent};
use PictaStudio\Venditio\Dto\Contracts\CartDtoContract;
use PictaStudio\Venditio\Models\Cart;

use function PictaStudio\Venditio\Helpers\Functions\get_fresh_model_instance;

class CartDto extends Dto implements CartDtoContract
{
    public function __construct(
        private Model $cart,
        private ?int $userId,
        private ?string $userFirstName,
        private ?string $userLastName,
        private ?string $userEmail,
        private ?string $discountRef,
        private ?int $shippingRateId,
        private ?array $addresses,
        private ?Collection $lines = null,
        private bool $linesProvided = false,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            cart: $data['cart'] ?? static::getFreshInstance(),
            userId: $data['user_id'] ?? null,
            userFirstName: $data['user_first_name'] ?? null,
            userLastName: $data['user_last_name'] ?? null,
            userEmail: $data['user_email'] ?? null,
            discountRef: $data['discount_code'] ?? $data['discount_ref'] ?? null,
            shippingRateId: isset($data['shipping_rate_id']) ? (int) $data['shipping_rate_id'] : null,
            addresses: $data['addresses'] ?? null,
            lines: collect($data['lines'] ?? [])
                ->map(fn (array $line) => CartLineDto::fromArray($line)),
            linesProvided: array_key_exists('lines', $data),
        );
    }

    public static function getFreshInstance(): Model
    {
        return get_fresh_model_instance('cart');
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'user_first_name' => $this->getUserFirstName(),
            'user_last_name' => $this->getUserLastName(),
            'user_email' => $this->getUserEmail(),
            'discount_code' => $this->getDiscountRef(),
            'shipping_rate_id' => $this->getShippingRateId(),
            'addresses' => $this->getAddresses(),
        ];
    }

    public function toModel(): Model
    {
        return $this->getCart()
            ->fill($this->toArray());
    }

    public function getCart(): Cart|Model
    {
        return $this->cart;
    }

    public function getUserId(): ?int
    {
        return $this->userId ?? auth()->guard()->id();
    }

    public function getUserFirstName(): ?string
    {
        return $this->userFirstName ?? $this->getCart()?->user_first_name;
    }

    public function getUserLastName(): ?string
    {
        return $this->userLastName ?? $this->getCart()?->user_last_name;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail ?? $this->getCart()?->user_email;
    }

    public function getDiscountRef(): ?string
    {
        return $this->discountRef ?? $this->getCart()?->discount_code;
    }

    public function getShippingRateId(): ?int
    {
        return $this->shippingRateId ?? $this->getCart()?->shipping_rate_id;
    }

    public function getAddresses(): ?array
    {
        $addresses = $this->addresses ?? $this->getCart()?->addresses;

        if ($addresses instanceof Fluent) {
            return $addresses->toArray();
        }

        return $addresses;
    }

    /**
     * @return Collection<CartLineDto>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function hasLinesProvided(): bool
    {
        return $this->linesProvided;
    }
}
