<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Product, ProductCollection};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_collection_product', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductCollection::class);
            $table->foreignIdFor(Product::class);
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_collection_product');
    }
};
