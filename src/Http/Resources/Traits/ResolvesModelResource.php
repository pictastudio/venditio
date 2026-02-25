<?php

namespace PictaStudio\Venditio\Http\Resources\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\V1\{AddressResource, BrandResource, CartLineResource, CartResource, CountryResource, CountryTaxClassResource, CurrencyResource, DiscountApplicationResource, DiscountResource, InventoryResource, MunicipalityResource, OrderLineResource, OrderResource, PriceListPriceResource, PriceListResource, ProductCategoryResource, ProductCustomFieldResource, ProductResource, ProductTypeResource, ProductVariantOptionResource, ProductVariantResource, ProvinceResource, RegionResource, ShippingStatusResource, TaxClassResource, UserResource};
use PictaStudio\Venditio\Models\{Address, Brand, Cart, CartLine, Country, CountryTaxClass, Currency, Discount, DiscountApplication, Inventory, Municipality, Order, OrderLine, PriceList, PriceListPrice, Product, ProductCategory, ProductCustomField, ProductType, ProductVariant, ProductVariantOption, Province, Region, ShippingStatus, TaxClass, User};

trait ResolvesModelResource
{
    protected function resolveResourceForModel(Model $model): JsonResource
    {
        return match (true) {
            $model instanceof Address => AddressResource::make($model),
            $model instanceof Brand => BrandResource::make($model),
            $model instanceof Cart => CartResource::make($model),
            $model instanceof CartLine => CartLineResource::make($model),
            $model instanceof Country => CountryResource::make($model),
            $model instanceof CountryTaxClass => CountryTaxClassResource::make($model),
            $model instanceof Currency => CurrencyResource::make($model),
            $model instanceof Discount => DiscountResource::make($model),
            $model instanceof DiscountApplication => DiscountApplicationResource::make($model),
            $model instanceof Inventory => InventoryResource::make($model),
            $model instanceof Municipality => MunicipalityResource::make($model),
            $model instanceof Order => OrderResource::make($model),
            $model instanceof OrderLine => OrderLineResource::make($model),
            $model instanceof PriceList => PriceListResource::make($model),
            $model instanceof PriceListPrice => PriceListPriceResource::make($model),
            $model instanceof Product => ProductResource::make($model),
            $model instanceof ProductCategory => ProductCategoryResource::make($model),
            $model instanceof ProductCustomField => ProductCustomFieldResource::make($model),
            $model instanceof ProductType => ProductTypeResource::make($model),
            $model instanceof ProductVariant => ProductVariantResource::make($model),
            $model instanceof ProductVariantOption => ProductVariantOptionResource::make($model),
            $model instanceof Province => ProvinceResource::make($model),
            $model instanceof Region => RegionResource::make($model),
            $model instanceof ShippingStatus => ShippingStatusResource::make($model),
            $model instanceof TaxClass => TaxClassResource::make($model),
            $model instanceof User => UserResource::make($model),
            default => JsonResource::make($model),
        };
    }
}
