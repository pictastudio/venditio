<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\Product;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_related_products', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Product::class);
            $table->foreignIdFor(Product::class, 'related_product_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->datetimes();

            $table->unique(['product_id', 'related_product_id'], 'venditio_product_related_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_related_products');
    }
};
