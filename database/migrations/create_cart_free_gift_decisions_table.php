<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Cart, FreeGift, Product};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_free_gift_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Cart::class);
            $table->foreignIdFor(FreeGift::class);
            $table->foreignIdFor(Product::class);
            $table->string('decision', 20)->comment('selected, declined');
            $table->datetimes();
            $table->unique(['cart_id', 'free_gift_id', 'product_id'], 'cart_free_gift_decisions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_free_gift_decisions');
    }
};
