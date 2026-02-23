<?php

use Illuminate\Support\Collection;
use PictaStudio\Venditio\Models\ShippingCarrier;
use PictaStudio\Venditio\Shipping\DefaultChargeableWeightCalculator;
use function PictaStudio\Venditio\Helpers\Functions\get_fresh_model_instance;

it('calculates chargeable weight using max of actual and volumetric with carrier rounding', function () {
    $calculator = new DefaultChargeableWeightCalculator();

    $carrier = new ShippingCarrier([
        'volumetric_divisor' => 5000,
        'weight_rounding_step_kg' => 0.5,
        'weight_rounding_mode' => 'ceil',
    ]);

    $line = get_fresh_model_instance('cart_line')->fill([
        'qty' => 2,
        'product_data' => [
            'weight' => 1,
            'length' => 10,
            'width' => 10,
            'height' => 10,
        ],
    ]);

    $result = $calculator->calculate(new Collection([$line]), $carrier);

    expect($result['actual_weight_kg'])->toBe(2.0)
        ->and($result['volumetric_weight_kg'])->toBe(0.4)
        ->and($result['chargeable_weight_kg'])->toBe(2.0);
});

it('handles higher volumetric weight and rounds by configured step', function () {
    $calculator = new DefaultChargeableWeightCalculator();

    $carrier = new ShippingCarrier([
        'volumetric_divisor' => 5000,
        'weight_rounding_step_kg' => 1,
        'weight_rounding_mode' => 'ceil',
    ]);

    $line = get_fresh_model_instance('cart_line')->fill([
        'qty' => 1,
        'product_data' => [
            'weight' => 1,
            'length' => 50,
            'width' => 40,
            'height' => 30,
        ],
    ]);

    $result = $calculator->calculate(new Collection([$line]), $carrier);

    expect($result['actual_weight_kg'])->toBe(1.0)
        ->and($result['volumetric_weight_kg'])->toBe(12.0)
        ->and($result['chargeable_weight_kg'])->toBe(12.0);
});
