<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\ProductVariant;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductVariant::class);
            $table->string('name');
            $table->string('hex_color', 20)->nullable();
            $table->smallInteger('sort_order');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->unique(['product_variant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_options');
    }
};
