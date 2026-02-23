<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\{Country, ShippingZone, ShippingZoneMember};

class ShippingZoneMemberFactory extends Factory
{
    protected $model = ShippingZoneMember::class;

    public function definition(): array
    {
        return [
            'shipping_zone_id' => ShippingZone::factory(),
            'zoneable_type' => 'country',
            'zoneable_id' => Country::factory(),
        ];
    }
}
