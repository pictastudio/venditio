<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Brand, Product, ProductCategory, ProductCollection, Tag};

use function Pest\Laravel\patchJson;

uses(RefreshDatabase::class);

it('normalizes seo metadata empty strings across catalog resources', function () {
    foreach (catalogMetadataUpdateCases() as [$model, $uri]) {
        $resource = $model::factory()->create([
            'active' => true,
        ]);

        patchJson($uri($resource) . '?exclude_all_scopes=1', [
            'metadata' => [
                'titolo' => 'SEO title',
                'autore' => '',
                'descrizione' => 'SEO description',
                'twitter_descrizione' => '',
            ],
        ])->assertOk()
            ->assertJsonPath('metadata.titolo', 'SEO title')
            ->assertJsonPath('metadata.autore', null)
            ->assertJsonPath('metadata.twitter_descrizione', null);

        expect($resource->refresh()->metadata)->toMatchArray([
            'titolo' => 'SEO title',
            'autore' => null,
            'descrizione' => 'SEO description',
            'twitter_descrizione' => null,
        ]);
    }
});

it('validates seo metadata fields across catalog resources', function () {
    foreach (catalogMetadataUpdateCases() as [$model, $uri]) {
        $resource = $model::factory()->create([
            'active' => true,
        ]);

        patchJson($uri($resource) . '?exclude_all_scopes=1', [
            'metadata' => [
                'twitter_titolo' => ['not a string'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('metadata.twitter_titolo');
    }
});

function catalogMetadataUpdateCases(): array
{
    $prefix = config('venditio.routes.api.v1.prefix');

    return [
        [ProductCollection::class, fn ($model): string => $prefix . '/product_collections/' . $model->getKey()],
        [ProductCategory::class, fn ($model): string => $prefix . '/product_categories/' . $model->getKey()],
        [Brand::class, fn ($model): string => $prefix . '/brands/' . $model->getKey()],
        [Tag::class, fn ($model): string => $prefix . '/tags/' . $model->getKey()],
        [Product::class, fn ($model): string => $prefix . '/products/' . $model->getKey()],
    ];
}
