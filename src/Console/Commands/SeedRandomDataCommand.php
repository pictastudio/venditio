<?php

namespace PictaStudio\Venditio\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\{Hash, Schema};
use PictaStudio\Venditio\Actions\Invoices\GenerateOrderInvoice;
use PictaStudio\Venditio\Actions\Taxes\{ExtractTaxFromGrossPrice, ResolveTaxRate};
use PictaStudio\Venditio\Contracts\{ShippingFeeCalculatorInterface, ShippingWeightsResolverInterface, ShippingZoneResolverInterface};
use PictaStudio\Venditio\Enums\DiscountType;
use PictaStudio\Venditio\Support\{CatalogImage, ProductMedia};
use Throwable;

use function PictaStudio\Venditio\Helpers\Functions\{get_fresh_model_instance, query, resolve_enum, resolve_model};

class SeedRandomDataCommand extends Command
{
    protected $signature = 'venditio:seed-random
        {--users=0 : Number of users to create (host app model dependent)}
        {--brands=10 : Number of brands}
        {--categories=10 : Number of categories}
        {--product-types=3 : Number of product types}
        {--products=50 : Number of products}
        {--product-variants=6 : Number of product variant definitions}
        {--options-per-variant=4 : Number of options for each product variant}
        {--discounts=12 : Number of discounts}
        {--shipping-statuses=3 : Number of shipping statuses}
        {--shipping-methods=3 : Number of shipping methods}
        {--shipping-zones=3 : Number of shipping zones}
        {--carts=20 : Number of carts}
        {--cart-lines=3 : Max lines per cart}
        {--orders=20 : Number of orders}
        {--order-lines=3 : Max lines per order}
        {--invoices=0 : Number of invoices to generate from seeded orders}
        {--price-lists=0 : Number of price lists (requires venditio.price_lists.enabled=true)}';

    protected $description = 'Seed random Venditio data for local/testing environments';

    private ?Collection $shippingDestinations = null;

    public function handle(): int
    {
        if (!config('venditio.commands.seed_random_data.enabled', true)) {
            $this->components->info('`venditio:seed-random` is disabled by configuration.');

            return self::SUCCESS;
        }

        $counts = $this->resolveCounts();
        $summary = collect([
            'users' => 0,
            'brands' => 0,
            'categories' => 0,
            'product_types' => 0,
            'products' => 0,
            'product_variants' => 0,
            'product_variant_options' => 0,
            'discounts' => 0,
            'shipping_statuses' => 0,
            'shipping_methods' => 0,
            'shipping_zones' => 0,
            'shipping_method_zones' => 0,
            'carts' => 0,
            'cart_lines' => 0,
            'orders' => 0,
            'order_lines' => 0,
            'invoices' => 0,
            'price_lists' => 0,
            'price_list_prices' => 0,
        ]);

        $currency = $this->ensureDefaultCurrency();
        $taxClass = $this->ensureDefaultTaxClass();

        $users = $this->seedUsers($counts->get('users', 0));
        $summary->put('users', $users->count());

        $brandIds = $this->seedBrands($counts->get('brands', 0));
        $summary->put('brands', $brandIds->count());

        $categoryIds = $this->seedCategories($counts->get('categories', 0));
        $summary->put('categories', $categoryIds->count());

        $productTypeCount = max(
            $counts->get('product_types', 0),
            $counts->get('products', 0) > 0 || $counts->get('product_variants', 0) > 0 ? 1 : 0
        );
        $productTypeIds = $this->seedProductTypes($productTypeCount);
        $summary->put('product_types', $productTypeIds->count());

        $products = $this->seedProducts(
            count: $counts->get('products', 0),
            taxClassId: $taxClass->getKey(),
            currencyId: $currency->getKey(),
            brandIds: $brandIds,
            categoryIds: $categoryIds,
            productTypeIds: $productTypeIds
        );
        $summary->put('products', $products->count());

        $variantSeed = $this->seedProductVariants(
            variantCount: $counts->get('product_variants', 0),
            optionsPerVariant: max(1, $counts->get('options_per_variant', 1)),
            productTypeIds: $productTypeIds
        );
        $summary->put('product_variants', $variantSeed['variants']);
        $summary->put('product_variant_options', $variantSeed['options']);

        $discountMap = $this->seedDiscounts(
            count: $counts->get('discounts', 0),
            products: $products
        );
        $summary->put('discounts', $discountMap->flatten(1)->count());

        $shippingStatuses = $this->seedShippingStatuses(max(
            $counts->get('shipping_statuses', 0),
            $counts->get('orders', 0) > 0 ? 1 : 0,
        ));
        $summary->put('shipping_statuses', $shippingStatuses->count());

        $shippingMethods = $this->seedShippingMethods($counts->get('shipping_methods', 0));
        $summary->put('shipping_methods', $shippingMethods->count());

        $shippingZoneSeed = $this->seedShippingZones(
            count: $counts->get('shipping_zones', 0),
            shippingMethods: $shippingMethods
        );
        $summary->put('shipping_zones', $shippingZoneSeed['zones']->count());
        $summary->put('shipping_method_zones', $shippingZoneSeed['shipping_method_zones']);

        $cartSeed = $this->seedCarts(
            count: $counts->get('carts', 0),
            maxLines: max(1, $counts->get('cart_lines', 1)),
            products: $products,
            users: $users,
            discountMap: $discountMap,
            shippingMethods: $shippingMethods,
            shippingZones: $shippingZoneSeed['zones']
        );
        $summary->put('carts', $cartSeed['carts']);
        $summary->put('cart_lines', $cartSeed['lines']);

        $orderSeed = $this->seedOrders(
            count: $counts->get('orders', 0),
            maxLines: max(1, $counts->get('order_lines', 1)),
            products: $products,
            users: $users,
            discountMap: $discountMap,
            shippingStatuses: $shippingStatuses,
            shippingMethods: $shippingMethods,
            shippingZones: $shippingZoneSeed['zones']
        );
        $summary->put('orders', $orderSeed['orders']);
        $summary->put('order_lines', $orderSeed['lines']);

        $summary->put(
            'invoices',
            $this->seedInvoices(
                count: $counts->get('invoices', 0),
                orders: $orderSeed['models']
            )
        );

        $priceListSeed = $this->seedPriceLists(
            count: $counts->get('price_lists', 0),
            products: $products
        );
        $summary->put('price_lists', $priceListSeed['price_lists']);
        $summary->put('price_list_prices', $priceListSeed['price_list_prices']);

        $this->newLine();
        $this->components->info('Random Venditio data seeded successfully.');
        $this->table(
            ['Entity', 'Created'],
            $summary
                ->map(fn (int $value, string $key): array => [str($key)->replace('_', ' ')->title()->value(), $value])
                ->values()
                ->all()
        );

        return self::SUCCESS;
    }

    private function resolveCounts(): Collection
    {
        return collect([
            'users' => $this->asNonNegativeInt('users'),
            'brands' => $this->asNonNegativeInt('brands'),
            'categories' => $this->asNonNegativeInt('categories'),
            'product_types' => $this->asNonNegativeInt('product-types'),
            'products' => $this->asNonNegativeInt('products'),
            'product_variants' => $this->asNonNegativeInt('product-variants'),
            'options_per_variant' => $this->asNonNegativeInt('options-per-variant'),
            'discounts' => $this->asNonNegativeInt('discounts'),
            'shipping_statuses' => $this->asNonNegativeInt('shipping-statuses'),
            'shipping_methods' => $this->asNonNegativeInt('shipping-methods'),
            'shipping_zones' => $this->asNonNegativeInt('shipping-zones'),
            'carts' => $this->asNonNegativeInt('carts'),
            'cart_lines' => $this->asNonNegativeInt('cart-lines'),
            'orders' => $this->asNonNegativeInt('orders'),
            'order_lines' => $this->asNonNegativeInt('order-lines'),
            'invoices' => $this->asNonNegativeInt('invoices'),
            'price_lists' => $this->asNonNegativeInt('price-lists'),
        ]);
    }

    private function asNonNegativeInt(string $optionName): int
    {
        return max(0, (int) $this->option($optionName));
    }

    private function ensureDefaultCurrency(): Model
    {
        $currencyModel = resolve_model('currency');

        $defaultCurrency = $currencyModel::query()
            ->where('is_default', true)
            ->first();

        if ($defaultCurrency instanceof Model) {
            return $defaultCurrency;
        }

        $currency = $currencyModel::query()->firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'EUR',
                'symbol' => 'EUR',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'is_enabled' => true,
                'is_default' => true,
            ]
        );

        if (!$currency->is_default) {
            $currency->update(['is_default' => true]);
            $currency->refresh();
        }

        return $currency;
    }

    private function ensureDefaultTaxClass(): Model
    {
        $taxClassModel = resolve_model('tax_class');

        $defaultTaxClass = $taxClassModel::query()
            ->where('is_default', true)
            ->first();

        if ($defaultTaxClass instanceof Model) {
            return $defaultTaxClass;
        }

        $existingTaxClass = $taxClassModel::query()->first();
        if ($existingTaxClass instanceof Model) {
            $existingTaxClass->update(['is_default' => true]);
            $existingTaxClass->refresh();

            return $existingTaxClass;
        }

        return $taxClassModel::query()->create([
            'name' => 'Standard',
            'is_default' => true,
        ]);
    }

    private function seedUsers(int $count): Collection
    {
        if ($count < 1) {
            return collect();
        }

        $userModel = resolve_model('user');
        $table = (new $userModel)->getTable();

        if (!Schema::hasTable($table)) {
            $this->components->warn("Users table `{$table}` does not exist. Skipping user seeding.");

            return collect();
        }

        $snapshots = $this->seedUsersFromFactory($userModel, $count);
        $remaining = $count - $snapshots->count();

        if ($remaining > 0) {
            $columns = Schema::getColumnListing($table);

            for ($index = 1; $index <= $remaining; $index++) {
                try {
                    $user = query('user')->create(
                        $this->buildUserPayload(
                            index: $index,
                            columns: $columns
                        )
                    );
                } catch (Throwable) {
                    $this->components->warn('Unable to fully seed users with fallback payload. Continuing without extra users.');
                    break;
                }

                $snapshots->push($this->extractUserSnapshot($user));
            }
        }

        return $snapshots->values();
    }

    private function seedUsersFromFactory(string $userModel, int $count): Collection
    {
        if (!method_exists($userModel, 'factory')) {
            return collect();
        }

        try {
            $users = $userModel::factory()->count($count)->create();
        } catch (Throwable) {
            return collect();
        }

        return $users
            ->filter(fn (mixed $user): bool => $user instanceof Model)
            ->map(fn (Model $user): array => $this->extractUserSnapshot($user))
            ->values();
    }

    private function buildUserPayload(int $index, array $columns): array
    {
        $timestamp = now()->timestamp;
        $email = "venditio-user-{$timestamp}-{$index}@example.test";
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        $payload = [];

        if (in_array('name', $columns, true)) {
            $payload['name'] = "{$firstName} {$lastName}";
        }

        if (in_array('first_name', $columns, true)) {
            $payload['first_name'] = $firstName;
        }

        if (in_array('last_name', $columns, true)) {
            $payload['last_name'] = $lastName;
        }

        if (in_array('email', $columns, true)) {
            $payload['email'] = $email;
        }

        if (in_array('phone', $columns, true)) {
            $payload['phone'] = fake()->phoneNumber();
        }

        if (in_array('password', $columns, true)) {
            $payload['password'] = Hash::make('password');
        }

        if (in_array('email_verified_at', $columns, true)) {
            $payload['email_verified_at'] = now();
        }

        if (in_array('remember_token', $columns, true)) {
            $payload['remember_token'] = Str::random(10);
        }

        return $payload;
    }

    private function extractUserSnapshot(Model $user): array
    {
        $name = (string) data_get($user, 'name', '');
        $firstName = (string) data_get($user, 'first_name', str($name)->before(' ')->value());
        $lastName = (string) data_get($user, 'last_name', str($name)->after(' ')->value());

        if ($firstName === '') {
            $firstName = fake()->firstName();
        }

        if ($lastName === '') {
            $lastName = fake()->lastName();
        }

        return [
            'id' => $user->getKey(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => (string) data_get($user, 'email', fake()->safeEmail()),
            'phone' => (string) data_get($user, 'phone', fake()->phoneNumber()),
        ];
    }

    private function seedBrands(int $count): Collection
    {
        $ids = collect();

        for ($i = 1; $i <= $count; $i++) {
            $name = "Brand {$i} " . mb_strtoupper(Str::random(4));

            $brand = query('brand')->create([
                'name' => $name,
                'abstract' => fake()->sentence(),
                'description' => fake()->paragraphs(2, true),
                'metadata' => [
                    'featured_tag' => 'brand-' . mb_strtolower(Str::random(6)),
                    'seed_source' => 'venditio:seed-random',
                ],
                'images' => $this->buildCatalogImages($name),
                'active' => true,
                'show_in_menu' => (bool) random_int(0, 1),
                'in_evidence' => (bool) random_int(0, 1),
                'sort_order' => $i,
            ]);

            $ids->push($brand->getKey());
        }

        return $ids;
    }

    private function seedCategories(int $count): Collection
    {
        $ids = collect();

        for ($i = 1; $i <= $count; $i++) {
            $name = "Category {$i} " . mb_strtoupper(Str::random(4));

            $category = query('product_category')->create([
                'name' => $name,
                'abstract' => fake()->sentence(),
                'description' => fake()->paragraphs(2, true),
                'metadata' => [
                    'seed_source' => 'venditio:seed-random',
                    'keywords' => fake()->words(3),
                ],
                'images' => $this->buildCatalogImages($name),
                'active' => true,
                'show_in_menu' => (bool) random_int(0, 1),
                'in_evidence' => (bool) random_int(0, 1),
                'sort_order' => $i,
                'visible_from' => now()->subDays(random_int(1, 30)),
                'visible_until' => now()->addDays(random_int(30, 365)),
            ]);

            $ids->push($category->getKey());
        }

        return $ids;
    }

    private function seedProductTypes(int $count): Collection
    {
        $ids = collect();
        $hasDefault = query('product_type')->where('is_default', true)->exists();

        for ($i = 1; $i <= $count; $i++) {
            $productType = query('product_type')->create([
                'name' => "Type {$i} " . mb_strtoupper(Str::random(4)),
                'active' => true,
                'is_default' => !$hasDefault && $i === 1,
            ]);

            $ids->push($productType->getKey());
        }

        return $ids;
    }

    private function seedProducts(
        int $count,
        mixed $taxClassId,
        mixed $currencyId,
        Collection $brandIds,
        Collection $categoryIds,
        Collection $productTypeIds
    ): Collection {
        $rows = collect();
        $statusEnum = config('venditio.product.status_enum');
        $unitEnum = config('venditio.product.measuring_unit_enum');

        for ($i = 1; $i <= $count; $i++) {
            $name = "Product {$i} " . mb_strtoupper(Str::random(5));
            $sku = 'SKU-' . now()->format('YmdHis') . '-' . $i . '-' . mb_strtoupper(Str::random(4));
            $brandId = $brandIds->isEmpty() ? null : $brandIds->random();
            $productTypeId = $productTypeIds->isEmpty() ? null : $productTypeIds->random();
            $media = $this->buildProductMedia($name);
            $status = collect($statusEnum::getActiveStatuses())
                ->map(fn (mixed $value) => is_object($value) && isset($value->value) ? $value->value : $value)
                ->filter(fn (mixed $value): bool => is_string($value) && filled($value))
                ->first() ?? collect($statusEnum::cases())->random()->value;

            $product = query('product')->create([
                'brand_id' => $brandId,
                'product_type_id' => $productTypeId,
                'tax_class_id' => $taxClassId,
                'name' => $name,
                'status' => $status,
                'active' => true,
                'new' => (bool) random_int(0, 1),
                'in_evidence' => (bool) random_int(0, 1),
                'sku' => $sku,
                'ean' => mb_str_pad((string) random_int(1, 9_999_999_999_999), 13, '0', STR_PAD_LEFT),
                'visible_from' => now()->subDays(random_int(0, 30)),
                'visible_until' => now()->addDays(random_int(30, 365)),
                'description' => fake()->paragraph(),
                'description_short' => fake()->sentence(),
                'images' => $media['images'],
                'files' => $media['files'],
                'measuring_unit' => collect($unitEnum::cases())->random()->value,
                'qty_for_unit' => random_int(1, 100),
                'length' => $this->randomMoney(1_000, 20_000),
                'width' => $this->randomMoney(1_000, 10_000),
                'height' => $this->randomMoney(500, 8_000),
                'weight' => $this->randomMoney(100, 5_000),
                'metadata' => ['keywords' => fake()->words(4)],
            ]);

            $manageStock = random_int(0, 100) <= 80;
            $stock = random_int(1, 300);
            $inventory = $product->inventory()->updateOrCreate([], [
                'currency_id' => $currencyId,
                'stock' => $stock,
                'stock_reserved' => 0,
                'stock_available' => $manageStock ? $stock : 0,
                'stock_min' => random_int(0, 30),
                'minimum_reorder_quantity' => random_int(1, 60),
                'reorder_lead_days' => random_int(1, 30),
                'manage_stock' => $manageStock,
                'price' => $this->randomMoney(500, 50_000),
                'price_includes_tax' => true,
                'purchase_price' => $this->randomMoney(300, 30_000),
            ]);

            if ($categoryIds->isNotEmpty()) {
                $attachCount = random_int(1, min(3, $categoryIds->count()));
                $attachIds = $categoryIds->shuffle()->take($attachCount)->all();
                $product->categories()->syncWithoutDetaching($attachIds);
            }

            $rows->push([
                'id' => $product->getKey(),
                'name' => $product->name,
                'sku' => $product->sku,
                'product_type_id' => $product->product_type_id,
                'tax_class_id' => $product->tax_class_id,
                'currency_id' => $inventory->currency_id,
                'price' => (float) $inventory->price,
                'purchase_price' => $inventory->purchase_price !== null ? (float) $inventory->purchase_price : null,
                'price_includes_tax' => (bool) $inventory->price_includes_tax,
                'manage_stock' => (bool) $inventory->manage_stock,
                'weight' => (float) $product->weight,
                'length' => (float) $product->length,
                'width' => (float) $product->width,
                'height' => (float) $product->height,
            ]);
        }

        return $rows;
    }

    private function seedProductVariants(int $variantCount, int $optionsPerVariant, Collection $productTypeIds): array
    {
        if ($variantCount < 1 || $productTypeIds->isEmpty()) {
            return ['variants' => 0, 'options' => 0];
        }

        $variantsCreated = 0;
        $optionsCreated = 0;

        for ($variantIndex = 1; $variantIndex <= $variantCount; $variantIndex++) {
            $variant = query('product_variant')->create([
                'product_type_id' => $productTypeIds->random(),
                'name' => "Variant {$variantIndex}",
                'sort_order' => $variantIndex,
            ]);

            $variantsCreated++;

            for ($optionIndex = 1; $optionIndex <= $optionsPerVariant; $optionIndex++) {
                query('product_variant_option')->create([
                    'product_variant_id' => $variant->getKey(),
                    'name' => "Option {$variantIndex}-{$optionIndex}",
                    'hex_color' => null,
                    'sort_order' => $optionIndex,
                ]);

                $optionsCreated++;
            }
        }

        return [
            'variants' => $variantsCreated,
            'options' => $optionsCreated,
        ];
    }

    private function seedDiscounts(int $count, Collection $products): Collection
    {
        if ($count < 1 || $products->isEmpty()) {
            return collect();
        }

        $discountMap = collect();

        for ($i = 1; $i <= $count; $i++) {
            $product = $products->random();
            $productId = $product['id'];
            $type = collect(DiscountType::cases())->random();

            $discount = query('discount')->create([
                'discountable_type' => 'product',
                'discountable_id' => $productId,
                'type' => $type->value,
                'value' => $type === DiscountType::Percentage
                    ? random_int(5, 30)
                    : $this->randomMoney(100, 3_000),
                'name' => "Discount {$i}",
                'code' => 'DISC-' . now()->format('YmdHis') . '-' . mb_str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'active' => true,
                'starts_at' => now()->subDays(random_int(1, 10)),
                'ends_at' => now()->addDays(random_int(5, 60)),
                'apply_to_cart_total' => false,
                'apply_once_per_cart' => (bool) random_int(0, 1),
                'max_uses_per_user' => null,
                'one_per_user' => false,
                'free_shipping' => false,
                'minimum_order_total' => null,
            ]);

            $productDiscounts = collect($discountMap->get($productId, []))
                ->push([
                    'id' => $discount->getKey(),
                    'code' => $discount->code,
                    'type' => is_object($discount->type) && isset($discount->type->value)
                        ? $discount->type->value
                        : $discount->type,
                    'value' => (float) $discount->value,
                    'apply_to_cart_total' => (bool) $discount->apply_to_cart_total,
                ])
                ->values();

            $discountMap->put($productId, $productDiscounts);
        }

        return $discountMap;
    }

    private function seedShippingStatuses(int $count): Collection
    {
        if ($count < 1) {
            return collect();
        }

        $statuses = collect();
        $names = collect([
            'Pending shipment',
            'Packed',
            'In transit',
            'Out for delivery',
            'Delivered',
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $status = query('shipping_status')->create([
                'external_code' => 'SHIP-STATUS-' . mb_strtoupper(Str::random(8)) . '-' . $i,
                'name' => $names->get(($i - 1) % $names->count()),
            ]);

            $statuses->push($status);
        }

        return $statuses;
    }

    private function seedShippingMethods(int $count): Collection
    {
        if ($count < 1) {
            return collect();
        }

        $methods = collect();
        $carriers = collect(['GLS', 'DHL', 'UPS', 'BRT', 'FedEx', 'TNT']);

        for ($i = 1; $i <= $count; $i++) {
            $carrier = $carriers->get(($i - 1) % $carriers->count());

            $methods->push(
                query('shipping_method')->create([
                    'code' => $carrier . '-' . mb_strtoupper(Str::random(4)),
                    'name' => $carrier . ' ' . fake()->city(),
                    'active' => true,
                    'flat_fee' => $this->randomMoney(500, 2_500),
                    'volumetric_divisor' => collect([4000, 5000, 6000])->random(),
                ])
            );
        }

        return $methods;
    }

    private function seedShippingZones(int $count, Collection $shippingMethods): array
    {
        if ($count < 1) {
            return [
                'zones' => collect(),
                'shipping_method_zones' => 0,
            ];
        }

        $destinations = $this->resolveShippingDestinations();
        if ($destinations->isEmpty()) {
            $this->components->warn('No countries/provinces are available. Skipping shipping zone seeding.');

            return [
                'zones' => collect(),
                'shipping_method_zones' => 0,
            ];
        }

        $scopes = ['province', 'region', 'country'];
        $zones = collect();
        $shippingMethodZones = 0;

        for ($index = 1; $index <= $count; $index++) {
            $preferredScope = $scopes[($index - 1) % count($scopes)];
            $destination = $this->resolveDestinationForScope($preferredScope, $destinations);
            $scope = $this->resolveScopeForDestination($preferredScope, $destination);
            $suffix = match ($scope) {
                'province' => (string) ($destination['province_code'] ?? $destination['province_name'] ?? 'province'),
                'region' => (string) ($destination['region_code'] ?? $destination['region_name'] ?? 'region'),
                default => (string) ($destination['country_iso_2'] ?? $destination['country'] ?? 'country'),
            };

            $zone = query('shipping_zone')->create([
                'code' => 'ZONE-' . mb_strtoupper(Str::slug($suffix, '-')) . '-' . mb_str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'name' => str($scope)->title()->append(' Zone ' . $index)->toString(),
                'active' => true,
                'priority' => random_int(0, 10),
            ]);

            if ($scope === 'province' && filled($destination['province_id'])) {
                $zone->provinces()->sync([(int) $destination['province_id']]);
            } elseif ($scope === 'region' && filled($destination['region_id'])) {
                $zone->regions()->sync([(int) $destination['region_id']]);
            } elseif (filled($destination['country_id'])) {
                $zone->countries()->sync([(int) $destination['country_id']]);
            }

            foreach ($shippingMethods as $shippingMethod) {
                query('shipping_method_zone')->create([
                    'shipping_method_id' => $shippingMethod->getKey(),
                    'shipping_zone_id' => $zone->getKey(),
                    'active' => true,
                    'rate_tiers' => [
                        ['max_weight' => 1, 'fee' => $this->randomMoney(400, 700)],
                        ['max_weight' => 5, 'fee' => $this->randomMoney(700, 1_200)],
                        ['max_weight' => 10, 'fee' => $this->randomMoney(1_200, 1_900)],
                        ['max_weight' => 20, 'fee' => $this->randomMoney(1_900, 3_000)],
                    ],
                    'over_weight_price_per_kg' => $this->randomMoney(120, 450),
                ]);

                $shippingMethodZones++;
            }

            $zones->push([
                'shipping_zone' => $zone,
                'scope' => $scope,
                'destination' => $destination,
            ]);
        }

        return [
            'zones' => $zones,
            'shipping_method_zones' => $shippingMethodZones,
        ];
    }

    private function seedCarts(
        int $count,
        int $maxLines,
        Collection $products,
        Collection $users,
        Collection $discountMap,
        Collection $shippingMethods,
        Collection $shippingZones
    ): array {
        if ($count < 1) {
            return ['carts' => 0, 'lines' => 0, 'models' => collect()];
        }

        $lineCount = 0;
        $statusEnum = resolve_enum('cart_status');
        $statuses = collect([
            $statusEnum::getProcessingStatus()->value,
            $statusEnum::getActiveStatus()->value,
            $statusEnum::getAbandonedStatus()->value,
            $statusEnum::getCancelledStatus()->value,
        ]);
        $carts = collect();

        for ($cartIndex = 1; $cartIndex <= $count; $cartIndex++) {
            $user = $users->isNotEmpty() && (bool) random_int(0, 1)
                ? $users->random()
                : null;
            $customer = $this->buildCustomerSnapshot($user);
            $shippingContext = $this->buildShippingSeedContext($shippingMethods, $shippingZones);
            $addresses = $this->buildSeedAddresses($customer, $shippingContext['destination']);
            $linePayloads = collect();

            if ($products->isNotEmpty()) {
                $cartLines = random_int(1, max(1, $maxLines));

                for ($lineIndex = 1; $lineIndex <= $cartLines; $lineIndex++) {
                    $product = $products->random();
                    $linePayloads->push(
                        $this->createLinePayload(
                            product: $product,
                            discountMap: $discountMap,
                            billingCountryId: is_numeric(data_get($addresses, 'billing.country_id'))
                                ? (int) data_get($addresses, 'billing.country_id')
                                : null
                        )
                    );
                }
            }

            $shippingData = $this->calculateShippingSnapshot(
                addresses: $addresses,
                linePayloads: $linePayloads,
                shippingMethod: $shippingContext['shipping_method']
            );
            $shippingFee = round((float) $shippingData['shipping_fee'], 2);
            $paymentFee = $this->randomMoney(0, 700);
            $totals = [
                'sub_total_taxable' => round((float) $linePayloads->sum(fn (array $line): float => $line['unit_final_price_taxable'] * $line['qty']), 2),
                'sub_total_tax' => round((float) $linePayloads->sum(fn (array $line): float => $line['unit_final_price_tax'] * $line['qty']), 2),
                'sub_total' => round((float) $linePayloads->sum(fn (array $line): float => $line['total_final_price']), 2),
            ];

            $cart = query('cart')->create([
                'user_id' => $customer['id'],
                'order_id' => null,
                'shipping_method_id' => $shippingContext['shipping_method']?->getKey(),
                'shipping_zone_id' => $shippingData['shipping_zone']?->getKey(),
                'identifier' => 'CART-' . now()->format('YmdHis') . '-' . mb_str_pad((string) $cartIndex, 4, '0', STR_PAD_LEFT),
                'status' => $statuses->random(),
                'sub_total_taxable' => $totals['sub_total_taxable'],
                'sub_total_tax' => $totals['sub_total_tax'],
                'sub_total' => $totals['sub_total'],
                'shipping_fee' => $shippingFee,
                'specific_weight' => $shippingData['specific_weight'],
                'volumetric_weight' => $shippingData['volumetric_weight'],
                'chargeable_weight' => $shippingData['chargeable_weight'],
                'payment_fee' => $paymentFee,
                'discount_code' => null,
                'discount_amount' => 0,
                'total_final' => round($totals['sub_total'] + $shippingFee + $paymentFee, 2),
                'user_first_name' => $customer['first_name'],
                'user_last_name' => $customer['last_name'],
                'user_email' => $customer['email'],
                'addresses' => $addresses,
                'notes' => fake()->sentence(),
            ]);

            foreach ($linePayloads as $linePayload) {
                query('cart_line')->create([
                    'cart_id' => $cart->getKey(),
                    ...$linePayload,
                ]);

                $lineCount++;
            }

            $carts->push($cart);
        }

        return [
            'carts' => $count,
            'lines' => $lineCount,
            'models' => $carts,
        ];
    }

    private function seedOrders(
        int $count,
        int $maxLines,
        Collection $products,
        Collection $users,
        Collection $discountMap,
        Collection $shippingStatuses,
        Collection $shippingMethods,
        Collection $shippingZones
    ): array {
        if ($count < 1) {
            return ['orders' => 0, 'lines' => 0, 'models' => collect()];
        }

        $lineCount = 0;
        $statusEnum = resolve_enum('order_status');
        $orders = collect();

        for ($orderIndex = 1; $orderIndex <= $count; $orderIndex++) {
            $user = $users->isNotEmpty() && (bool) random_int(0, 1)
                ? $users->random()
                : null;
            $customer = $this->buildCustomerSnapshot($user);
            $shippingContext = $this->buildShippingSeedContext($shippingMethods, $shippingZones);
            $addresses = $this->buildSeedAddresses($customer, $shippingContext['destination']);
            $linePayloads = collect();

            if ($products->isNotEmpty()) {
                $orderLines = random_int(1, max(1, $maxLines));

                for ($lineIndex = 1; $lineIndex <= $orderLines; $lineIndex++) {
                    $product = $products->random();
                    $linePayloads->push(
                        $this->createLinePayload(
                            product: $product,
                            discountMap: $discountMap,
                            billingCountryId: is_numeric(data_get($addresses, 'billing.country_id'))
                                ? (int) data_get($addresses, 'billing.country_id')
                                : null
                        )
                    );
                }
            }

            $shippingData = $this->calculateShippingSnapshot(
                addresses: $addresses,
                linePayloads: $linePayloads,
                shippingMethod: $shippingContext['shipping_method']
            );
            $shippingFee = round((float) $shippingData['shipping_fee'], 2);
            $paymentFee = $this->randomMoney(0, 700);
            $totals = [
                'sub_total_taxable' => round((float) $linePayloads->sum(fn (array $line): float => $line['unit_final_price_taxable'] * $line['qty']), 2),
                'sub_total_tax' => round((float) $linePayloads->sum(fn (array $line): float => $line['unit_final_price_tax'] * $line['qty']), 2),
                'sub_total' => round((float) $linePayloads->sum(fn (array $line): float => $line['total_final_price']), 2),
            ];
            $status = collect($statusEnum::cases())->random()->value;

            $order = query('order')->create([
                'user_id' => $customer['id'],
                'shipping_status_id' => $shippingStatuses->random()->getKey(),
                'shipping_method_id' => $shippingContext['shipping_method']?->getKey(),
                'shipping_zone_id' => $shippingData['shipping_zone']?->getKey(),
                'identifier' => 'ORD-' . now()->format('YmdHis') . '-' . mb_str_pad((string) $orderIndex, 4, '0', STR_PAD_LEFT),
                'status' => $status,
                'tracking_code' => mb_strtoupper(Str::random(12)),
                'tracking_link' => fake()->url(),
                'last_tracked_at' => now(),
                'courier_code' => collect(['UPS', 'DHL', 'FEDEX', 'GLS', 'BRT'])->random(),
                'sub_total_taxable' => $totals['sub_total_taxable'],
                'sub_total_tax' => $totals['sub_total_tax'],
                'sub_total' => $totals['sub_total'],
                'shipping_fee' => $shippingFee,
                'specific_weight' => $shippingData['specific_weight'],
                'volumetric_weight' => $shippingData['volumetric_weight'],
                'chargeable_weight' => $shippingData['chargeable_weight'],
                'payment_fee' => $paymentFee,
                'discount_code' => null,
                'discount_amount' => 0,
                'total_final' => round($totals['sub_total'] + $shippingFee + $paymentFee, 2),
                'user_first_name' => $customer['first_name'],
                'user_last_name' => $customer['last_name'],
                'user_email' => $customer['email'],
                'addresses' => $addresses,
                'shipping_method_data' => $shippingContext['shipping_method']?->toArray(),
                'shipping_zone_data' => $shippingData['shipping_zone']?->toArray(),
                'customer_notes' => fake()->sentence(),
                'admin_notes' => fake()->sentence(),
                'approved_at' => $status !== $statusEnum::getProcessingStatus()->value
                    ? now()->subMinutes(random_int(1, 120))
                    : null,
            ]);

            foreach ($linePayloads as $linePayload) {
                query('order_line')->create([
                    'order_id' => $order->getKey(),
                    ...$linePayload,
                ]);

                $lineCount++;
            }

            $orders->push($order);
        }

        return [
            'orders' => $count,
            'lines' => $lineCount,
            'models' => $orders,
        ];
    }

    private function seedInvoices(int $count, Collection $orders): int
    {
        if ($count < 1 || $orders->isEmpty()) {
            return 0;
        }

        if (!config('venditio.invoices.enabled', false)) {
            $this->components->warn('Invoices are disabled (`venditio.invoices.enabled=false`). Skipping invoice seeding.');

            return 0;
        }

        $requiredSellerFields = ['name', 'address_line_1', 'city', 'postal_code', 'country'];
        $missingSellerFields = collect($requiredSellerFields)
            ->reject(fn (string $key): bool => filled(config('venditio.invoices.seller.' . $key)));

        if ($missingSellerFields->isNotEmpty()) {
            $this->components->warn('Invoice seller configuration is incomplete. Skipping invoice seeding.');

            return 0;
        }

        $createdInvoices = 0;
        $invoiceGenerator = app(GenerateOrderInvoice::class);

        foreach ($orders->take($count) as $order) {
            try {
                $result = $invoiceGenerator->handle($order);
            } catch (Throwable) {
                $this->components->warn('Unable to generate one of the requested invoices. Continuing with the remaining orders.');

                continue;
            }

            if ((bool) ($result['created'] ?? false)) {
                $createdInvoices++;
            }
        }

        return $createdInvoices;
    }

    private function createLinePayload(array $product, Collection $discountMap, ?int $billingCountryId = null): array
    {
        $productId = $product['id'];
        $unitPrice = max(0.01, (float) ($product['price'] ?? $this->randomMoney(500, 20_000)));
        $qty = random_int(1, 5);
        $productDiscounts = collect($discountMap->get($productId, []))
            ->reject(fn (array $discount): bool => (bool) ($discount['apply_to_cart_total'] ?? false))
            ->values();
        $discount = $productDiscounts->isNotEmpty() && random_int(0, 100) <= 35
            ? $productDiscounts->random()
            : null;
        $unitDiscount = $this->resolveLineDiscountAmount($unitPrice, $discount);
        $unitFinalPrice = round(max(0, $unitPrice - $unitDiscount), 2);
        $taxRate = app(ResolveTaxRate::class)->handle(
            $product['tax_class_id'] ?? null,
            $billingCountryId,
        );
        $priceIncludesTax = (bool) ($product['price_includes_tax'] ?? true);

        if ($priceIncludesTax) {
            $taxBreakdown = app(ExtractTaxFromGrossPrice::class)->handle($unitFinalPrice, $taxRate);
            $unitFinalPriceTaxable = $taxBreakdown['taxable'];
            $unitFinalPriceTax = $taxBreakdown['tax'];
        } else {
            $unitFinalPriceTaxable = $unitFinalPrice;
            $unitFinalPriceTax = round($unitFinalPrice * ($taxRate / 100), 2);
        }

        return [
            'product_id' => $productId,
            'currency_id' => $product['currency_id'],
            'discount_id' => is_array($discount) ? $discount['id'] : null,
            'product_name' => $product['name'],
            'product_sku' => $product['sku'],
            'discount_code' => is_array($discount) ? $discount['code'] : null,
            'discount_amount' => round($unitDiscount * $qty, 2),
            'unit_price' => $unitPrice,
            'purchase_price' => $product['purchase_price'],
            'unit_discount' => $unitDiscount,
            'unit_final_price' => $unitFinalPrice,
            'unit_final_price_tax' => $unitFinalPriceTax,
            'unit_final_price_taxable' => $unitFinalPriceTaxable,
            'qty' => $qty,
            'total_final_price' => round(($unitFinalPriceTaxable + $unitFinalPriceTax) * $qty, 2),
            'tax_rate' => (float) $taxRate,
            'product_data' => [
                'id' => $productId,
                'name' => $product['name'],
                'sku' => $product['sku'],
                'tax_class_id' => $product['tax_class_id'],
                'weight' => $product['weight'],
                'length' => $product['length'],
                'width' => $product['width'],
                'height' => $product['height'],
                'inventory' => [
                    'currency_id' => $product['currency_id'],
                    'price' => $unitPrice,
                    'purchase_price' => $product['purchase_price'],
                    'price_includes_tax' => $priceIncludesTax,
                    'manage_stock' => (bool) ($product['manage_stock'] ?? true),
                ],
                'price_calculated' => [
                    'price' => $unitPrice,
                    'price_final' => $unitFinalPrice,
                    'purchase_price' => $product['purchase_price'],
                    'price_includes_tax' => $priceIncludesTax,
                    'price_source' => 'inventory',
                ],
            ],
        ];
    }

    private function resolveLineDiscountAmount(float $unitPrice, ?array $discount): float
    {
        if (!is_array($discount)) {
            return 0.0;
        }

        $type = $discount['type'] ?? null;
        $value = (float) ($discount['value'] ?? 0);

        $amount = match ($type) {
            DiscountType::Percentage->value => round($unitPrice * ($value / 100), 2),
            DiscountType::Fixed->value => round($value, 2),
            default => 0.0,
        };

        return round(min($unitPrice, max(0, $amount)), 2);
    }

    private function buildCatalogImages(string $name): array
    {
        return CatalogImage::normalizeCollection([
            [
                'type' => 'thumb',
                'name' => Str::slug($name) . '-thumb.jpg',
                'alt' => $name . ' thumb',
                'mimetype' => 'image/jpeg',
                'src' => fake()->imageUrl(),
                'sort_order' => 10,
            ],
            [
                'type' => 'cover',
                'name' => Str::slug($name) . '-cover.jpg',
                'alt' => $name . ' cover',
                'mimetype' => 'image/jpeg',
                'src' => fake()->imageUrl(),
                'sort_order' => 20,
            ],
        ]);
    }

    private function buildProductMedia(string $name): array
    {
        return ProductMedia::normalizeProductMedia(
            [
                [
                    'name' => Str::slug($name) . '-hero.jpg',
                    'alt' => $name . ' hero',
                    'mimetype' => 'image/jpeg',
                    'src' => fake()->imageUrl(),
                    'sort_order' => 10,
                    'active' => true,
                    'thumbnail' => true,
                ],
                [
                    'name' => Str::slug($name) . '-detail.jpg',
                    'alt' => $name . ' detail',
                    'mimetype' => 'image/jpeg',
                    'src' => fake()->imageUrl(),
                    'sort_order' => 20,
                    'active' => true,
                    'thumbnail' => false,
                ],
            ],
            [
                [
                    'name' => Str::slug($name) . '-spec-sheet.pdf',
                    'alt' => $name . ' spec sheet',
                    'mimetype' => 'application/pdf',
                    'src' => fake()->url(),
                    'sort_order' => 10,
                    'active' => true,
                ],
            ]
        );
    }

    private function buildCustomerSnapshot(?array $user): array
    {
        return [
            'id' => is_array($user) ? $user['id'] : null,
            'first_name' => is_array($user) ? $user['first_name'] : fake()->firstName(),
            'last_name' => is_array($user) ? $user['last_name'] : fake()->lastName(),
            'email' => is_array($user) ? $user['email'] : fake()->safeEmail(),
            'phone' => is_array($user) ? ($user['phone'] ?? fake()->phoneNumber()) : fake()->phoneNumber(),
            'sex' => collect(['m', 'f'])->random(),
        ];
    }

    private function buildSeedAddresses(array $customer, array $destination): array
    {
        return [
            'billing' => $this->buildAddressSnapshot($customer, $destination, 'billing'),
            'shipping' => $this->buildAddressSnapshot($customer, $destination, 'shipping'),
        ];
    }

    private function buildAddressSnapshot(array $customer, array $destination, string $type): array
    {
        $zip = (string) ($destination['zip'] ?? fake()->numerify('#####'));
        $companyName = random_int(0, 100) <= 40 ? fake()->company() : null;
        $countryName = $destination['country'] ?? null;
        $stateCode = $destination['province_code']
            ?? $destination['region_code']
            ?? $destination['country_iso_2']
            ?? mb_strtoupper(Str::random(2));

        return [
            'type' => $type,
            'country_id' => $destination['country_id'] ?? null,
            'province_id' => $destination['province_id'] ?? null,
            'country' => $countryName,
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'email' => $customer['email'],
            'sex' => $customer['sex'],
            'phone' => $customer['phone'],
            'company_name' => $companyName,
            'vat_number' => fake()->numerify('###########'),
            'fiscal_code' => mb_strtoupper(fake()->bothify('??##??##??##??##')),
            'sdi' => mb_strtoupper(fake()->bothify('????###')),
            'pec' => 'pec+' . Str::slug($customer['first_name'] . '-' . $customer['last_name']) . '@example.test',
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => random_int(0, 100) <= 35 ? fake()->secondaryAddress() : null,
            'city' => $destination['city']
                ?? $destination['province_name']
                ?? $destination['region_name']
                ?? $countryName
                ?? fake()->city(),
            'state' => mb_strtoupper(mb_substr((string) $stateCode, 0, 10)),
            'zip' => mb_substr($zip, 0, 10),
            'notes' => $type === 'billing'
                ? 'Seeded billing snapshot'
                : 'Seeded shipping snapshot',
        ];
    }

    private function buildShippingSeedContext(Collection $shippingMethods, Collection $shippingZones): array
    {
        $zoneSeed = $shippingZones->isNotEmpty()
            ? $shippingZones->random()
            : null;

        return [
            'shipping_method' => $shippingMethods->isNotEmpty()
                ? $shippingMethods->random()
                : null,
            'destination' => is_array(data_get($zoneSeed, 'destination'))
                ? data_get($zoneSeed, 'destination')
                : $this->randomShippingDestination(),
        ];
    }

    private function calculateShippingSnapshot(array $addresses, Collection $linePayloads, ?Model $shippingMethod): array
    {
        $cartPrototype = get_fresh_model_instance('cart');
        $cartPrototype->fill([
            'shipping_method_id' => $shippingMethod?->getKey(),
            'addresses' => $addresses,
        ]);
        $cartPrototype->setRelation(
            'lines',
            $linePayloads->map(function (array $linePayload): Model {
                $line = get_fresh_model_instance('cart_line');
                $line->fill($linePayload);

                return $line;
            })
        );

        $weights = app(ShippingWeightsResolverInterface::class)->resolve($cartPrototype, $shippingMethod);
        $shippingFee = 0.0;
        $shippingZone = null;
        $strategy = $this->resolveShippingStrategy();

        if ($linePayloads->isEmpty() || !$shippingMethod instanceof Model || $strategy === 'disabled') {
            return [
                'specific_weight' => round((float) ($weights['specific_weight'] ?? 0), 2),
                'volumetric_weight' => round((float) ($weights['volumetric_weight'] ?? 0), 2),
                'chargeable_weight' => round((float) ($weights['chargeable_weight'] ?? 0), 2),
                'shipping_fee' => 0.0,
                'shipping_zone' => null,
            ];
        }

        try {
            if ($strategy === 'flat') {
                $shippingFee = app(ShippingFeeCalculatorInterface::class)->calculate(
                    $strategy,
                    $cartPrototype,
                    $shippingMethod,
                );
            } elseif ($strategy === 'zones') {
                $resolvedZone = app(ShippingZoneResolverInterface::class)->resolve($cartPrototype, $shippingMethod);
                $candidateZone = $resolvedZone['shipping_zone'] ?? null;
                $shippingMethodZone = $resolvedZone['shipping_method_zone'] ?? null;

                if ($candidateZone instanceof Model && $shippingMethodZone instanceof Model) {
                    $shippingZone = $candidateZone;
                    $shippingFee = app(ShippingFeeCalculatorInterface::class)->calculate(
                        $strategy,
                        $cartPrototype,
                        $shippingMethod,
                        $shippingMethodZone,
                    );
                }
            }
        } catch (Throwable) {
            $shippingFee = 0.0;
            $shippingZone = null;
        }

        return [
            'specific_weight' => round((float) ($weights['specific_weight'] ?? 0), 2),
            'volumetric_weight' => round((float) ($weights['volumetric_weight'] ?? 0), 2),
            'chargeable_weight' => round((float) ($weights['chargeable_weight'] ?? 0), 2),
            'shipping_fee' => round($shippingFee, 2),
            'shipping_zone' => $shippingZone,
        ];
    }

    private function resolveShippingStrategy(): string
    {
        $strategy = mb_strtolower((string) config('venditio.shipping.strategy', 'disabled'));

        return in_array($strategy, ['disabled', 'flat', 'zones'], true)
            ? $strategy
            : 'disabled';
    }

    private function resolveShippingDestinations(): Collection
    {
        if ($this->shippingDestinations instanceof Collection) {
            return $this->shippingDestinations;
        }

        $this->ensureShippingSeedGeography();

        $destinations = query('province')
            ->with(['region.country'])
            ->get()
            ->filter(fn (Model $province): bool => $province->relationLoaded('region') && $province->region instanceof Model && $province->region->relationLoaded('country') && $province->region->country instanceof Model)
            ->map(function (Model $province): array {
                $region = $province->region;
                $country = $region->country;

                return [
                    'country_id' => (int) $country->getKey(),
                    'country' => (string) $country->name,
                    'country_iso_2' => (string) $country->iso_2,
                    'region_id' => (int) $region->getKey(),
                    'region_name' => (string) $region->name,
                    'region_code' => (string) $region->code,
                    'province_id' => (int) $province->getKey(),
                    'province_name' => (string) $province->name,
                    'province_code' => (string) $province->code,
                    'city' => (string) $province->name,
                    'zip' => fake()->numerify('#####'),
                ];
            })
            ->values();

        if ($destinations->isEmpty()) {
            $destinations = query('country')
                ->get()
                ->map(fn (Model $country): array => [
                    'country_id' => (int) $country->getKey(),
                    'country' => (string) $country->name,
                    'country_iso_2' => (string) $country->iso_2,
                    'region_id' => null,
                    'region_name' => null,
                    'region_code' => null,
                    'province_id' => null,
                    'province_name' => null,
                    'province_code' => null,
                    'city' => (string) ($country->capital ?? $country->name),
                    'zip' => fake()->numerify('#####'),
                ])
                ->values();
        }

        $this->shippingDestinations = $destinations;

        return $this->shippingDestinations;
    }

    private function ensureShippingSeedGeography(): void
    {
        if (
            query('country')->exists()
            && query('region')->exists()
            && query('province')->exists()
        ) {
            return;
        }

        $currencyId = $this->ensureDefaultCurrency()->getKey();

        $country = query('country')->firstOrCreate(
            [
                'iso_2' => 'IT',
            ],
            [
                'currency_id' => $currencyId,
                'name' => 'Italy',
                'iso_3' => 'ITA',
                'phone_code' => '+39',
                'flag_emoji' => 'it',
                'capital' => 'Rome',
                'native' => 'Italia',
            ]
        );

        $region = query('region')->firstOrCreate(
            [
                'country_id' => $country->getKey(),
                'code' => 'LAZ',
            ],
            [
                'name' => 'Lazio',
            ]
        );

        query('province')->firstOrCreate(
            [
                'region_id' => $region->getKey(),
                'code' => 'RM',
            ],
            [
                'name' => 'Roma',
            ]
        );
    }

    private function randomShippingDestination(): array
    {
        $destinations = $this->resolveShippingDestinations();

        if ($destinations->isNotEmpty()) {
            return $destinations->random();
        }

        return [
            'country_id' => null,
            'country' => fake()->country(),
            'country_iso_2' => mb_strtoupper(fake()->lexify('??')),
            'region_id' => null,
            'region_name' => null,
            'region_code' => null,
            'province_id' => null,
            'province_name' => null,
            'province_code' => null,
            'city' => fake()->city(),
            'zip' => fake()->numerify('#####'),
        ];
    }

    private function resolveDestinationForScope(string $preferredScope, Collection $destinations): array
    {
        $orderedScopes = collect([$preferredScope, ...collect(['province', 'region', 'country'])->reject(fn (string $scope): bool => $scope === $preferredScope)->all()]);

        foreach ($orderedScopes as $scope) {
            $matchingDestinations = $destinations
                ->filter(function (array $destination) use ($scope): bool {
                    return match ($scope) {
                        'province' => filled($destination['province_id'] ?? null),
                        'region' => filled($destination['region_id'] ?? null),
                        default => filled($destination['country_id'] ?? null),
                    };
                });

            if ($matchingDestinations->isNotEmpty()) {
                return $matchingDestinations->random();
            }
        }

        return $this->randomShippingDestination();
    }

    private function resolveScopeForDestination(string $preferredScope, array $destination): string
    {
        if ($preferredScope === 'province' && filled($destination['province_id'] ?? null)) {
            return 'province';
        }

        if ($preferredScope === 'region' && filled($destination['region_id'] ?? null)) {
            return 'region';
        }

        if (filled($destination['province_id'] ?? null)) {
            return 'province';
        }

        if (filled($destination['region_id'] ?? null)) {
            return 'region';
        }

        if (filled($destination['country_id'] ?? null)) {
            return 'country';
        }

        return 'country';
    }

    private function seedPriceLists(int $count, Collection $products): array
    {
        if ($count < 1) {
            return ['price_lists' => 0, 'price_list_prices' => 0];
        }

        if (!config('venditio.price_lists.enabled', false)) {
            $this->components->warn('Price lists are disabled (`venditio.price_lists.enabled=false`). Skipping price list seeding.');

            return ['price_lists' => 0, 'price_list_prices' => 0];
        }

        if ($products->isEmpty()) {
            return ['price_lists' => 0, 'price_list_prices' => 0];
        }

        $priceListPricesCount = 0;

        for ($i = 1; $i <= $count; $i++) {
            $priceList = query('price_list')->create([
                'name' => "Price List {$i}",
                'code' => 'PL-' . now()->format('YmdHis') . '-' . mb_str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'active' => true,
                'description' => fake()->sentence(),
                'metadata' => ['seed_source' => 'venditio:seed-random'],
            ]);

            $productsToAttach = $products
                ->shuffle()
                ->take(random_int(1, min(10, $products->count())));

            foreach ($productsToAttach as $product) {
                query('price_list_price')->updateOrCreate(
                    [
                        'product_id' => $product['id'],
                        'price_list_id' => $priceList->getKey(),
                    ],
                    [
                        'price' => $this->randomMoney(500, 40_000),
                        'purchase_price' => $this->randomMoney(300, 25_000),
                        'price_includes_tax' => true,
                        'is_default' => false,
                        'metadata' => ['seed_source' => 'venditio:seed-random'],
                    ]
                );

                $priceListPricesCount++;
            }
        }

        return [
            'price_lists' => $count,
            'price_list_prices' => $priceListPricesCount,
        ];
    }

    private function randomMoney(int $minCents, int $maxCents): float
    {
        return round(random_int($minCents, $maxCents) / 100, 2);
    }
}
