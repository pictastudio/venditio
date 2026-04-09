<?php

namespace PictaStudio\Venditio\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\{Model, ModelNotFoundException};
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ResolveVenditioRouteBindings
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route === null) {
            return $next($request);
        }

        foreach ($this->routeModelBindings() as $parameter => $model) {
            $value = $route->parameter($parameter);

            if ($value === null || $value instanceof Model) {
                continue;
            }

            $modelClass = resolve_model($model);

            if (!is_string($modelClass) || !is_a($modelClass, Model::class, true)) {
                continue;
            }

            $bindingField = method_exists($route, 'bindingFieldFor')
                ? $route->bindingFieldFor($parameter)
                : null;

            $boundModel = (new $modelClass)->resolveRouteBinding($value, $bindingField);

            if ($boundModel === null) {
                throw (new ModelNotFoundException)->setModel($modelClass, [$value]);
            }

            $route->setParameter($parameter, $boundModel);
        }

        return $next($request);
    }

    protected function routeModelBindings(): array
    {
        return [
            'address' => 'address',
            'brand' => 'brand',
            'cart' => 'cart',
            'cart_line' => 'cart_line',
            'country' => 'country',
            'country_tax_class' => 'country_tax_class',
            'currency' => 'currency',
            'discount' => 'discount',
            'discount_application' => 'discount_application',
            'inventory' => 'inventory',
            'municipality' => 'municipality',
            'order' => 'order',
            'order_line' => 'order_line',
            'price_list' => 'price_list',
            'price_list_price' => 'price_list_price',
            'product' => 'product',
            'product_category' => 'product_category',
            'product_custom_field' => 'product_custom_field',
            'product_type' => 'product_type',
            'product_variant' => 'product_variant',
            'productVariantOption' => 'product_variant_option',
            'product_variant_option' => 'product_variant_option',
            'province' => 'province',
            'region' => 'region',
            'shipping_status' => 'shipping_status',
            'tag' => 'tag',
            'tax_class' => 'tax_class',
        ];
    }
}
