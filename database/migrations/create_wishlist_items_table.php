<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Product, Wishlist};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('venditio.wishlists.tables.wishlist_items', 'wishlist_items'), function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Wishlist::class)->index();
            $table->foreignIdFor(Product::class)->index();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->unique(['wishlist_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('venditio.wishlists.tables.wishlist_items', 'wishlist_items'));
    }
};
