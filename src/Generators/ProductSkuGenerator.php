<?php

namespace PictaStudio\Venditio\Generators;

use PictaStudio\Venditio\Contracts\ProductSkuGeneratorInterface;
use PictaStudio\Venditio\Models\Product;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ProductSkuGenerator implements ProductSkuGeneratorInterface
{
    public function forProductPayload(array $payload): string
    {
        return $this->generateIncrementalSku();
    }

    public function forVariant(Product $baseProduct, array $options): string
    {
        return $this->generateIncrementalSku();
    }

    private function generateIncrementalSku(): string
    {
        $prefix = $this->resolvePrefix();
        $padding = $this->resolveCounterPadding();
        $counter = $this->latestGeneratedCounter($prefix) + 1;
        $candidate = $this->buildCandidate($prefix, $counter, $padding);

        while ($this->skuExists($candidate)) {
            $counter++;
            $candidate = $this->buildCandidate($prefix, $counter, $padding);
        }

        return $candidate;
    }

    private function latestGeneratedCounter(string $prefix): int
    {
        $model = query('product')->getModel();
        $skuColumn = $model->qualifyColumn('sku');
        $keyColumn = $model->qualifyColumn($model->getKeyName());

        $candidates = query('product')
            ->withoutGlobalScopes()
            ->where('sku', 'like', $this->escapeLikePattern($prefix) . '%')
            ->orderByDesc($keyColumn)
            ->pluck($skuColumn);

        foreach ($candidates as $candidate) {
            $counter = $this->extractCounter((string) $candidate, $prefix);

            if ($counter !== null) {
                return $counter;
            }
        }

        return 0;
    }

    private function skuExists(string $sku): bool
    {
        return query('product')
            ->withoutGlobalScopes()
            ->where('sku', $sku)
            ->exists();
    }

    private function resolvePrefix(): string
    {
        $prefix = mb_trim((string) config('venditio.product.sku_prefix', 'SW-'));

        if ($prefix === '') {
            $prefix = 'SW-';
        }

        return mb_substr($prefix, 0, 240);
    }

    private function resolveCounterPadding(): int
    {
        return max(0, (int) config('venditio.product.sku_counter_padding', 0));
    }

    private function buildCandidate(string $prefix, int $counter, int $padding): string
    {
        $counterString = $this->formatCounter($counter, $padding);
        $maxPrefixLength = max(1, 255 - mb_strlen($counterString));
        $normalizedPrefix = mb_substr($prefix, 0, $maxPrefixLength);

        return $normalizedPrefix . $counterString;
    }

    private function formatCounter(int $counter, int $padding): string
    {
        return str_pad((string) $counter, $padding, '0', STR_PAD_LEFT);
    }

    private function extractCounter(string $sku, string $prefix): ?int
    {
        if (!str_starts_with($sku, $prefix)) {
            return null;
        }

        $counter = mb_substr($sku, mb_strlen($prefix));

        if ($counter === '' || !ctype_digit($counter)) {
            return null;
        }

        return (int) $counter;
    }

    private function escapeLikePattern(string $value): string
    {
        return addcslashes($value, "\\%_");
    }
}
