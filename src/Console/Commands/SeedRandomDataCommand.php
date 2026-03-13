<?php

namespace PictaStudio\Venditio\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\{Hash, Schema};
use PictaStudio\Venditio\Enums\DiscountType;
use Throwable;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_enum, resolve_model};

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
        {--carts=20 : Number of carts}
        {--cart-lines=3 : Max lines per cart}
        {--orders=20 : Number of orders}
        {--order-lines=3 : Max lines per order}
        {--price-lists=0 : Number of price lists (requires venditio.price_lists.enabled=true)}';

    protected $description = 'Seed random Venditio data for local/testing environments';

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
            'carts' => 0,
            'cart_lines' => 0,
            'orders' => 0,
            'order_lines' => 0,
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

        $cartSeed = $this->seedCarts(
            count: $counts->get('carts', 0),
            maxLines: max(1, $counts->get('cart_lines', 1)),
            products: $products,
            users: $users,
            discountMap: $discountMap
        );
        $summary->put('carts', $cartSeed['carts']);
        $summary->put('cart_lines', $cartSeed['lines']);

        $orderSeed = $this->seedOrders(
            count: $counts->get('orders', 0),
            maxLines: max(1, $counts->get('order_lines', 1)),
            products: $products,
            users: $users,
            discountMap: $discountMap
        );
        $summary->put('orders', $orderSeed['orders']);
        $summary->put('order_lines', $orderSeed['lines']);

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
            'carts' => $this->asNonNegativeInt('carts'),
            'cart_lines' => $this->asNonNegativeInt('cart-lines'),
            'orders' => $this->asNonNegativeInt('orders'),
            'order_lines' => $this->asNonNegativeInt('order-lines'),
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
                } catch (Throwable $throwable) {
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
        } catch (Throwable $throwable) {
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
        ];
    }

    private function seedBrands(int $count): Collection
    {
        $ids = collect();

        for ($i = 1; $i <= $count; $i++) {
            $brand = query('brand')->create([
                'name' => "Brand {$i} " . mb_strtoupper(Str::random(4)),
                'active' => true,
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
            $category = query('product_category')->create([
                'name' => "Category {$i} " . mb_strtoupper(Str::random(4)),
                'active' => true,
                'sort_order' => $i,
            ]);

            $ids->push($category->getKey());
        }

        return $ids;
    }

    private function seedProductTypes(int $count): Collection
    {
        $ids = collect();

        for ($i = 1; $i <= $count; $i++) {
            $productType = query('product_type')->create([
                'name' => "Type {$i} " . mb_strtoupper(Str::random(4)),
                'active' => true,
                'is_default' => $i === 1,
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

            $product = query('product')->create([
                'brand_id' => $brandId,
                'product_type_id' => $productTypeId,
                'tax_class_id' => $taxClassId,
                'name' => $name,
                'status' => collect($statusEnum::cases())->random()->value,
                'active' => true,
                'new' => (bool) random_int(0, 1),
                'in_evidence' => (bool) random_int(0, 1),
                'sku' => $sku,
                'ean' => mb_str_pad((string) random_int(1, 9_999_999_999_999), 13, '0', STR_PAD_LEFT),
                'visible_from' => now()->subDays(random_int(0, 30)),
                'visible_until' => now()->addDays(random_int(30, 365)),
                'description' => fake()->paragraph(),
                'description_short' => fake()->sentence(),
                'images' => [['alt' => $name, 'src' => fake()->imageUrl()]],
                'files' => [['alt' => $name, 'src' => fake()->url()]],
                'measuring_unit' => collect($unitEnum::cases())->random()->value,
                'qty_for_unit' => random_int(1, 100),
                'length' => $this->randomMoney(10, 1_000),
                'width' => $this->randomMoney(10, 1_000),
                'height' => $this->randomMoney(10, 1_000),
                'weight' => $this->randomMoney(10, 1_000),
                'metadata' => ['keywords' => fake()->words(4)],
            ]);

            $inventory = $product->inventory()->updateOrCreate([], [
                'currency_id' => $currencyId,
                'stock' => random_int(1, 300),
                'stock_reserved' => 0,
                'stock_available' => 0,
                'stock_min' => random_int(0, 30),
                'minimum_reorder_quantity' => random_int(1, 60),
                'reorder_lead_days' => random_int(1, 30),
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
                'currency_id' => $inventory->currency_id,
                'price' => (float) $inventory->price,
                'purchase_price' => $inventory->purchase_price !== null ? (float) $inventory->purchase_price : null,
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
                    'image' => null,
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

            $discount = query('discount')->create([
                'discountable_type' => 'product',
                'discountable_id' => $productId,
                'type' => collect(DiscountType::cases())->random()->value,
                'value' => $this->randomMoney(100, 3_000),
                'name' => "Discount {$i}",
                'code' => 'DISC-' . now()->format('YmdHis') . '-' . mb_str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'active' => true,
                'starts_at' => now()->subDays(random_int(1, 10)),
                'ends_at' => now()->addDays(random_int(5, 60)),
                'apply_to_cart_total' => (bool) random_int(0, 1),
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
                ])
                ->values();

            $discountMap->put($productId, $productDiscounts);
        }

        return $discountMap;
    }

    private function seedCarts(
        int $count,
        int $maxLines,
        Collection $products,
        Collection $users,
        Collection $discountMap
    ): array {
        if ($count < 1) {
            return ['carts' => 0, 'lines' => 0];
        }

        $lineCount = 0;
        $statusEnum = resolve_enum('cart_status');

        for ($cartIndex = 1; $cartIndex <= $count; $cartIndex++) {
            $user = $users->isNotEmpty() && (bool) random_int(0, 1)
                ? $users->random()
                : null;
            $userFirstName = is_array($user) ? $user['first_name'] : fake()->firstName();
            $userLastName = is_array($user) ? $user['last_name'] : fake()->lastName();
            $userEmail = is_array($user) ? $user['email'] : fake()->safeEmail();
            $shippingFee = $this->randomMoney(0, 1_500);
            $paymentFee = $this->randomMoney(0, 700);

            $cart = query('cart')->create([
                'user_id' => is_array($user) ? $user['id'] : null,
                'order_id' => null,
                'identifier' => 'CART-' . now()->format('YmdHis') . '-' . mb_str_pad((string) $cartIndex, 4, '0', STR_PAD_LEFT),
                'status' => collect($statusEnum::cases())->random()->value,
                'sub_total_taxable' => 0,
                'sub_total_tax' => 0,
                'sub_total' => 0,
                'shipping_fee' => $shippingFee,
                'payment_fee' => $paymentFee,
                'discount_code' => null,
                'discount_amount' => 0,
                'total_final' => 0,
                'user_first_name' => $userFirstName,
                'user_last_name' => $userLastName,
                'user_email' => $userEmail,
                'addresses' => [
                    'billing' => [],
                    'shipping' => [],
                ],
                'notes' => fake()->sentence(),
            ]);

            $totals = [
                'sub_total_taxable' => 0.0,
                'sub_total_tax' => 0.0,
                'sub_total' => 0.0,
                'discount_amount' => 0.0,
            ];

            if ($products->isNotEmpty()) {
                $cartLines = random_int(1, max(1, $maxLines));

                for ($lineIndex = 1; $lineIndex <= $cartLines; $lineIndex++) {
                    $product = $products->random();
                    $line = $this->createLinePayload($product, $discountMap);

                    query('cart_line')->create([
                        'cart_id' => $cart->getKey(),
                        ...$line,
                    ]);

                    $lineCount++;
                    $totals['sub_total_taxable'] += $line['unit_final_price_taxable'] * $line['qty'];
                    $totals['sub_total_tax'] += $line['unit_final_price_tax'] * $line['qty'];
                    $totals['sub_total'] += $line['unit_final_price'] * $line['qty'];
                    $totals['discount_amount'] += $line['discount_amount'];
                }
            }

            $cart->update([
                'sub_total_taxable' => round($totals['sub_total_taxable'], 2),
                'sub_total_tax' => round($totals['sub_total_tax'], 2),
                'sub_total' => round($totals['sub_total'], 2),
                'discount_amount' => round($totals['discount_amount'], 2),
                'total_final' => round($totals['sub_total'] + $shippingFee + $paymentFee, 2),
            ]);
        }

        return [
            'carts' => $count,
            'lines' => $lineCount,
        ];
    }

    private function seedOrders(
        int $count,
        int $maxLines,
        Collection $products,
        Collection $users,
        Collection $discountMap
    ): array {
        if ($count < 1) {
            return ['orders' => 0, 'lines' => 0];
        }

        $shippingStatusKey = (new (resolve_model('shipping_status')))->getKeyName();
        $shippingStatusIds = query('shipping_status')->pluck($shippingStatusKey)->values();
        if ($shippingStatusIds->isEmpty()) {
            $shippingStatus = query('shipping_status')->create([
                'external_code' => 'SHIPPED-' . mb_strtoupper(Str::random(8)),
                'name' => 'In transit',
            ]);
            $shippingStatusIds->push($shippingStatus->getKey());
        }

        $lineCount = 0;
        $statusEnum = resolve_enum('order_status');

        for ($orderIndex = 1; $orderIndex <= $count; $orderIndex++) {
            $user = $users->isNotEmpty() && (bool) random_int(0, 1)
                ? $users->random()
                : null;
            $userFirstName = is_array($user) ? $user['first_name'] : fake()->firstName();
            $userLastName = is_array($user) ? $user['last_name'] : fake()->lastName();
            $userEmail = is_array($user) ? $user['email'] : fake()->safeEmail();
            $shippingFee = $this->randomMoney(0, 1_500);
            $paymentFee = $this->randomMoney(0, 700);

            $order = query('order')->create([
                'user_id' => is_array($user) ? $user['id'] : null,
                'shipping_status_id' => $shippingStatusIds->random(),
                'identifier' => 'ORD-' . now()->format('YmdHis') . '-' . mb_str_pad((string) $orderIndex, 4, '0', STR_PAD_LEFT),
                'status' => collect($statusEnum::cases())->random()->value,
                'tracking_code' => mb_strtoupper(Str::random(12)),
                'tracking_link' => fake()->url(),
                'last_tracked_at' => now(),
                'courier_code' => collect(['UPS', 'DHL', 'FEDEX'])->random(),
                'sub_total_taxable' => 0,
                'sub_total_tax' => 0,
                'sub_total' => 0,
                'shipping_fee' => $shippingFee,
                'payment_fee' => $paymentFee,
                'discount_code' => null,
                'discount_amount' => 0,
                'total_final' => 0,
                'user_first_name' => $userFirstName,
                'user_last_name' => $userLastName,
                'user_email' => $userEmail,
                'addresses' => [
                    'billing' => [],
                    'shipping' => [],
                ],
                'customer_notes' => fake()->sentence(),
                'admin_notes' => fake()->sentence(),
                'approved_at' => now()->subMinutes(random_int(1, 120)),
            ]);

            $totals = [
                'sub_total_taxable' => 0.0,
                'sub_total_tax' => 0.0,
                'sub_total' => 0.0,
                'discount_amount' => 0.0,
            ];

            if ($products->isNotEmpty()) {
                $orderLines = random_int(1, max(1, $maxLines));

                for ($lineIndex = 1; $lineIndex <= $orderLines; $lineIndex++) {
                    $product = $products->random();
                    $line = $this->createLinePayload($product, $discountMap);

                    query('order_line')->create([
                        'order_id' => $order->getKey(),
                        ...$line,
                    ]);

                    $lineCount++;
                    $totals['sub_total_taxable'] += $line['unit_final_price_taxable'] * $line['qty'];
                    $totals['sub_total_tax'] += $line['unit_final_price_tax'] * $line['qty'];
                    $totals['sub_total'] += $line['unit_final_price'] * $line['qty'];
                    $totals['discount_amount'] += $line['discount_amount'];
                }
            }

            $order->update([
                'sub_total_taxable' => round($totals['sub_total_taxable'], 2),
                'sub_total_tax' => round($totals['sub_total_tax'], 2),
                'sub_total' => round($totals['sub_total'], 2),
                'discount_amount' => round($totals['discount_amount'], 2),
                'total_final' => round($totals['sub_total'] + $shippingFee + $paymentFee, 2),
            ]);
        }

        return [
            'orders' => $count,
            'lines' => $lineCount,
        ];
    }

    private function createLinePayload(array $product, Collection $discountMap): array
    {
        $productId = $product['id'];
        $unitPrice = max(0.01, (float) ($product['price'] ?? $this->randomMoney(500, 20_000)));
        $qty = random_int(1, 5);
        $maxDiscountCents = max(0, (int) floor($unitPrice * 100 * 0.20));
        $unitDiscount = $maxDiscountCents > 0
            ? random_int(0, $maxDiscountCents) / 100
            : 0.0;
        $unitFinalPriceTaxable = round(max(0, $unitPrice - $unitDiscount), 2);
        $taxRate = collect([4, 10, 22])->random();
        $unitFinalPriceTax = round($unitFinalPriceTaxable * ($taxRate / 100), 2);
        $unitFinalPrice = round($unitFinalPriceTaxable + $unitFinalPriceTax, 2);

        $discount = null;
        $productDiscounts = collect($discountMap->get($productId, []));

        if ($productDiscounts->isNotEmpty() && random_int(0, 100) <= 35) {
            $discount = $productDiscounts->random();
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
            'total_final_price' => round($unitFinalPrice * $qty, 2),
            'tax_rate' => (float) $taxRate,
            'product_data' => [
                'id' => $productId,
                'name' => $product['name'],
                'sku' => $product['sku'],
            ],
        ];
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
                'metadata' => null,
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
                        'metadata' => null,
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
