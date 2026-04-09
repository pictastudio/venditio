<?php

namespace PictaStudio\Venditio\Discounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Contracts\DiscountablesResolverInterface;

use function PictaStudio\Venditio\Helpers\Functions\query;

class DiscountablesResolver implements DiscountablesResolverInterface
{
    /**
     * @return Collection<int, Model>
     */
    public function resolve(Model $line, DiscountContext $context): Collection
    {
        $discountables = collect([$line]);

        $product = $this->resolveProduct($line);

        if ($product instanceof Model) {
            $discountables->push($product);

            if ($product->relationLoaded('parent') && $product->parent instanceof Model) {
                $discountables->push($product->parent);
            }

            if ($product->relationLoaded('brand') && $product->brand instanceof Model) {
                $discountables->push($product->brand);
            }

            if ($product->relationLoaded('productType') && $product->productType instanceof Model) {
                $discountables->push($product->productType);
            }

            if ($product->relationLoaded('categories')) {
                $discountables = $discountables->merge(
                    $product->categories->filter(fn (mixed $category) => $category instanceof Model)
                );
            }

            if ($product->relationLoaded('collections')) {
                $discountables = $discountables->merge(
                    $product->collections->filter(fn (mixed $collection) => $collection instanceof Model)
                );
            }
        }

        $discountables = $discountables
            ->push($context->getCart())
            ->push($context->getOrder())
            ->push($context->getUser())
            ->filter(fn (mixed $model) => $model instanceof Model && filled($model->getKey()))
            ->unique(fn (Model $model) => implode(':', [$model->getMorphClass(), (string) $model->getKey()]));

        return $discountables->values();
    }

    private function resolveProduct(Model $line): ?Model
    {
        if ($line->relationLoaded('product') && $line->product instanceof Model) {
            return $line->product;
        }

        $productId = $line->getAttribute('product_id');

        if (blank($productId)) {
            return null;
        }

        return query('product')
            ->withoutGlobalScopes()
            ->with(['categories', 'collections', 'brand', 'productType', 'parent'])
            ->find($productId);
    }
}
