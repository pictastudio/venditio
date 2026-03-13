<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{PriceList, Product};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Product::class)->index();
            $table->foreignIdFor(PriceList::class)->index();
            $table->decimal('price', 10, 2);
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->boolean('price_includes_tax')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->unique(['product_id', 'price_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_prices');
    }
};
