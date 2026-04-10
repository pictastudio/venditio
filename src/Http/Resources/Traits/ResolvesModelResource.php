<?php

namespace PictaStudio\Venditio\Http\Resources\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\V1\{AddressResource, BrandResource, CartLineResource, CartResource, CountryResource, CountryTaxClassResource, CurrencyResource, DiscountApplicationResource, DiscountResource, FreeGiftResource, InventoryResource, MunicipalityResource, OrderLineResource, OrderResource, PriceListPriceResource, PriceListResource, ProductCategoryResource, ProductCollectionResource, ProductCustomFieldResource, ProductResource, ProductTypeResource, ProductVariantOptionResource, ProductVariantResource, ProvinceResource, RegionResource, ReturnReasonResource, ReturnRequestResource, ShippingMethodResource, ShippingMethodZoneResource, ShippingStatusResource, ShippingZoneResource, TaxClassResource, UserResource};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

trait ResolvesModelResource
{
    protected function resolveResourceForModel(Model $model): JsonResource
    {
        foreach ($this->resourceMap() as $modelKey => $resourceClass) {
            $modelClass = resolve_model($modelKey);

            if (is_string($modelClass) && is_a($model, $modelClass)) {
                return $resourceClass::make($model);
            }
        }

        return JsonResource::make($model);
    }

    protected function resourceMap(): array
    {
        return [
            'address' => AddressResource::class,
            'brand' => BrandResource::class,
            'cart' => CartResource::class,
            'cart_line' => CartLineResource::class,
            'country' => CountryResource::class,
            'country_tax_class' => CountryTaxClassResource::class,
            'currency' => CurrencyResource::class,
            'discount' => DiscountResource::class,
            'discount_application' => DiscountApplicationResource::class,
            'free_gift' => FreeGiftResource::class,
            'inventory' => InventoryResource::class,
            'municipality' => MunicipalityResource::class,
            'order' => OrderResource::class,
            'order_line' => OrderLineResource::class,
            'price_list' => PriceListResource::class,
            'price_list_price' => PriceListPriceResource::class,
            'product' => ProductResource::class,
            'product_category' => ProductCategoryResource::class,
            'product_collection' => ProductCollectionResource::class,
            'product_custom_field' => ProductCustomFieldResource::class,
            'product_type' => ProductTypeResource::class,
            'product_variant' => ProductVariantResource::class,
            'product_variant_option' => ProductVariantOptionResource::class,
            'province' => ProvinceResource::class,
            'region' => RegionResource::class,
            'return_reason' => ReturnReasonResource::class,
            'return_request' => ReturnRequestResource::class,
            'shipping_method' => ShippingMethodResource::class,
            'shipping_method_zone' => ShippingMethodZoneResource::class,
            'shipping_status' => ShippingStatusResource::class,
            'shipping_zone' => ShippingZoneResource::class,
            'tax_class' => TaxClassResource::class,
            'user' => UserResource::class,
        ];
    }
}
