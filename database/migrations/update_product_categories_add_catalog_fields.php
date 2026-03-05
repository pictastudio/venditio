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
            $table->json('img_thumb')->nullable()->after('metadata');
            $table->json('img_cover')->nullable()->after('img_thumb');
            $table->boolean('show_in_menu')->default(false)->after('active');
            $table->boolean('in_evidence')->default(false)->after('show_in_menu');
            $table->dateTime('visible_from')->nullable()->index()->after('sort_order');
            $table->dateTime('visible_until')->nullable()->index()->after('visible_from');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropIndex(['visible_from']);
            $table->dropIndex(['visible_until']);

            $table->dropColumn([
                'abstract',
                'description',
                'metadata',
                'img_thumb',
                'img_cover',
                'show_in_menu',
                'in_evidence',
                'visible_from',
                'visible_until',
            ]);
        });
    }
};
