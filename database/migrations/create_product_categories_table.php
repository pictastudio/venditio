<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\ProductCategory;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductCategory::class, 'parent_id')->nullable();
            $table->string('path')->nullable()->index()->comment('path of the category in the tree');
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->smallInteger('sort_order');
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
