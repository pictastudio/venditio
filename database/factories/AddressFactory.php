<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\Address;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(config('venditio.addresses.type_enum')::cases())->value,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'sex' => fake()->randomElement(['m', 'f']),
            'phone' => fake()->phoneNumber(),
            'vat_number' => fake()->randomNumber(11),
            'fiscal_code' => fake()->randomNumber(16),
            'sdi' => mb_strtoupper(fake()->bothify('???????')),
            'pec' => fake()->safeEmail(),
            'company_name' => fake()->company(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'zip' => fake()->postcode(),
            'birth_date' => fake()->date(),
            'birth_place' => fake()->city(),
            'notes' => fake()->sentences(asText: true),
        ];
    }
}
