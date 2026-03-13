<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureImagesColumn('brands');
        $this->migrateLegacyImagesToCollection('brands');
        $this->dropLegacyImageColumns('brands');

        $this->ensureImagesColumn('product_categories');
        $this->migrateLegacyImagesToCollection('product_categories');
        $this->dropLegacyImageColumns('product_categories');
    }

    public function down(): void
    {
        $this->ensureLegacyImageColumns('brands');
        $this->migrateCollectionToLegacyImages('brands');
        $this->dropImagesColumn('brands');

        $this->ensureLegacyImageColumns('product_categories');
        $this->migrateCollectionToLegacyImages('product_categories');
        $this->dropImagesColumn('product_categories');
    }

    private function ensureImagesColumn(string $tableName): void
    {
        if (Schema::hasColumn($tableName, 'images')) {
            return;
        }

        $afterColumn = Schema::hasColumn($tableName, 'metadata')
            ? 'metadata'
            : 'description';

        Schema::table($tableName, function (Blueprint $table) use ($afterColumn) {
            $table->json('images')->nullable()->after($afterColumn);
        });
    }

    private function ensureLegacyImageColumns(string $tableName): void
    {
        if (!Schema::hasColumn($tableName, 'img_thumb')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->json('img_thumb')->nullable()->after(
                    Schema::hasColumn($tableName, 'metadata') ? 'metadata' : 'description'
                );
            });
        }

        if (!Schema::hasColumn($tableName, 'img_cover')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->json('img_cover')->nullable()->after(
                    Schema::hasColumn($tableName, 'img_thumb')
                        ? 'img_thumb'
                        : (Schema::hasColumn($tableName, 'metadata') ? 'metadata' : 'description')
                );
            });
        }
    }

    private function dropLegacyImageColumns(string $tableName): void
    {
        $columns = collect(['img_thumb', 'img_cover'])
            ->filter(fn (string $column): bool => Schema::hasColumn($tableName, $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    private function dropImagesColumn(string $tableName): void
    {
        if (!Schema::hasColumn($tableName, 'images')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }

    private function migrateLegacyImagesToCollection(string $tableName): void
    {
        if (
            !Schema::hasColumn($tableName, 'images')
            || (!Schema::hasColumn($tableName, 'img_thumb') && !Schema::hasColumn($tableName, 'img_cover'))
        ) {
            return;
        }

        $columns = ['id', 'images'];

        if (Schema::hasColumn($tableName, 'img_thumb')) {
            $columns[] = 'img_thumb';
        }

        if (Schema::hasColumn($tableName, 'img_cover')) {
            $columns[] = 'img_cover';
        }

        DB::table($tableName)
            ->select($columns)
            ->orderBy('id')
            ->get()
            ->each(function (object $record) use ($tableName): void {
                $existingImages = $this->decodeJson($record->images ?? null);

                if ($existingImages !== []) {
                    return;
                }

                $images = array_values(array_filter([
                    $this->legacyImageToCollectionItem($record->img_thumb ?? null, 'thumb'),
                    $this->legacyImageToCollectionItem($record->img_cover ?? null, 'cover'),
                ]));

                if ($images === []) {
                    return;
                }

                DB::table($tableName)
                    ->where('id', $record->id)
                    ->update([
                        'images' => json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
            });
    }

    private function migrateCollectionToLegacyImages(string $tableName): void
    {
        if (
            !Schema::hasColumn($tableName, 'images')
            || !Schema::hasColumn($tableName, 'img_thumb')
            || !Schema::hasColumn($tableName, 'img_cover')
        ) {
            return;
        }

        DB::table($tableName)
            ->select(['id', 'images'])
            ->orderBy('id')
            ->get()
            ->each(function (object $record) use ($tableName): void {
                $images = collect($this->decodeJson($record->images ?? null))
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->values();

                DB::table($tableName)
                    ->where('id', $record->id)
                    ->update([
                        'img_thumb' => $this->legacyColumnValue($images->firstWhere('type', 'thumb')),
                        'img_cover' => $this->legacyColumnValue($images->firstWhere('type', 'cover')),
                    ]);
            });
    }

    private function legacyImageToCollectionItem(mixed $value, string $type): ?array
    {
        $image = $this->decodeJson($value);

        if ($image === [] || !is_array($image) || blank($image['src'] ?? null)) {
            return null;
        }

        return [
            'id' => (string) Str::ulid(),
            'type' => $type,
            'name' => $image['name'] ?? null,
            'alt' => $image['alt'] ?? null,
            'mimetype' => $image['mimetype'] ?? null,
            'src' => $image['src'],
        ];
    }

    private function legacyColumnValue(mixed $image): ?string
    {
        if (!is_array($image) || blank($image['src'] ?? null)) {
            return null;
        }

        return json_encode([
            'name' => $image['name'] ?? null,
            'alt' => $image['alt'] ?? null,
            'mimetype' => $image['mimetype'] ?? null,
            'src' => $image['src'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }
};
