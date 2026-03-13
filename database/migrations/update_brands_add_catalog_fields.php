<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->text('abstract')->nullable()->after('slug');
            $table->text('description')->nullable()->after('abstract');
            $table->json('metadata')->nullable()->after('description');
            $table->json('images')->nullable()->after('metadata');
            $table->boolean('active')->default(true)->after('name');
            $table->boolean('show_in_menu')->default(false)->after('active');
            $table->boolean('in_evidence')->default(false)->after('show_in_menu');
            $table->smallInteger('sort_order')->default(0)->after('in_evidence');
        });
    }

    public function down(): void
    {
        $columns = collect([
            'abstract',
            'description',
            'metadata',
            'images',
            'active',
            'show_in_menu',
            'in_evidence',
            'sort_order',
        ])
            ->filter(fn (string $column): bool => Schema::hasColumn('brands', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        Schema::table('brands', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
