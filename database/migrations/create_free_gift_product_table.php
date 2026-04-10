<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{FreeGift, Product};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_gift_product', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(FreeGift::class);
            $table->foreignIdFor(Product::class);
            $table->datetimes();
            $table->unique(['free_gift_id', 'product_id'], 'free_gift_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_gift_product');
    }
};
