<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->text('abstract')->nullable()->after('slug');
            $table->text('description')->nullable()->after('abstract');
            $table->json('metadata')->nullable()->after('description');
            $table->json('images')->nullable()->after('metadata');
            $table->boolean('show_in_menu')->default(false)->after('active');
            $table->boolean('in_evidence')->default(false)->after('show_in_menu');
            $table->dateTime('visible_from')->nullable()->index()->after('sort_order');
            $table->dateTime('visible_until')->nullable()->index()->after('visible_from');
        });
    }

    public function down(): void
    {
        $hasVisibleFrom = Schema::hasColumn('product_categories', 'visible_from');
        $hasVisibleUntil = Schema::hasColumn('product_categories', 'visible_until');
        $columns = collect([
            'abstract',
            'description',
            'metadata',
            'images',
            'show_in_menu',
            'in_evidence',
            'visible_from',
            'visible_until',
        ])
            ->filter(fn (string $column): bool => Schema::hasColumn('product_categories', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        Schema::table('product_categories', function (Blueprint $table) use ($columns, $hasVisibleFrom, $hasVisibleUntil) {
            if ($hasVisibleFrom) {
                $table->dropIndex(['visible_from']);
            }

            if ($hasVisibleUntil) {
                $table->dropIndex(['visible_until']);
            }

            $table->dropColumn($columns);
        });
    }
};
