<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureImagesColumn();
        $this->migrateLegacyImagesToCollection();
        $this->dropLegacyImageColumns();
    }

    public function down(): void
    {
        $this->ensureLegacyImageColumns();
        $this->migrateCollectionToLegacyImages();
        $this->dropImagesColumn();
    }

    private function ensureImagesColumn(): void
    {
        if (Schema::hasColumn('tags', 'images')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table): void {
            $table->json('images')->nullable()->after('metadata');
        });
    }

    private function ensureLegacyImageColumns(): void
    {
        if (!Schema::hasColumn('tags', 'img_thumb')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->json('img_thumb')->nullable()->after('metadata');
            });
        }

        if (!Schema::hasColumn('tags', 'img_cover')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->json('img_cover')->nullable()->after('img_thumb');
            });
        }
    }

    private function dropLegacyImageColumns(): void
    {
        $columns = collect(['img_thumb', 'img_cover'])
            ->filter(fn (string $column): bool => Schema::hasColumn('tags', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        Schema::table('tags', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    private function dropImagesColumn(): void
    {
        if (!Schema::hasColumn('tags', 'images')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table): void {
            $table->dropColumn('images');
        });
    }

    private function migrateLegacyImagesToCollection(): void
    {
        if (
            !Schema::hasColumn('tags', 'images')
            || (!Schema::hasColumn('tags', 'img_thumb') && !Schema::hasColumn('tags', 'img_cover'))
        ) {
            return;
        }

        $columns = ['id', 'images'];

        if (Schema::hasColumn('tags', 'img_thumb')) {
            $columns[] = 'img_thumb';
        }

        if (Schema::hasColumn('tags', 'img_cover')) {
            $columns[] = 'img_cover';
        }

        DB::table('tags')
            ->select($columns)
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                if ($this->decodeJson($record->images ?? null) !== []) {
                    return;
                }

                $images = array_values(array_filter([
                    $this->legacyImageToCollectionItem($record->img_thumb ?? null, 'thumb'),
                    $this->legacyImageToCollectionItem($record->img_cover ?? null, 'cover'),
                ]));

                if ($images === []) {
                    return;
                }

                DB::table('tags')
                    ->where('id', $record->id)
                    ->update([
                        'images' => json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
            });
    }

    private function migrateCollectionToLegacyImages(): void
    {
        if (
            !Schema::hasColumn('tags', 'images')
            || !Schema::hasColumn('tags', 'img_thumb')
            || !Schema::hasColumn('tags', 'img_cover')
        ) {
            return;
        }

        DB::table('tags')
            ->select(['id', 'images'])
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                $images = collect($this->decodeJson($record->images ?? null))
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->values();

                DB::table('tags')
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

        if ($image === [] || blank($image['src'] ?? null)) {
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
