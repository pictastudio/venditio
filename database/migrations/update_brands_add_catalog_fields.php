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
            $table->json('img_thumb')->nullable()->after('metadata');
            $table->json('img_cover')->nullable()->after('img_thumb');
            $table->boolean('active')->default(true)->after('name');
            $table->boolean('show_in_menu')->default(false)->after('active');
            $table->boolean('in_evidence')->default(false)->after('show_in_menu');
            $table->smallInteger('sort_order')->default(0)->after('in_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'abstract',
                'description',
                'metadata',
                'img_thumb',
                'img_cover',
                'active',
                'show_in_menu',
                'in_evidence',
                'sort_order',
            ]);
        });
    }
};
