<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

it('does not disable wrapping for non-venditio json resources', function () {
    $response = TestExternalJsonResource::make(['id' => 1])
        ->toResponse(Request::create('/external-resource', 'GET'))
        ->getData(true);

    expect($response)->toBe([
        'data' => [
            'id' => 1,
        ],
    ]);
});

class TestExternalJsonResource extends JsonResource {}
