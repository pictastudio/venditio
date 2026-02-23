<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\ShippingRate;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rate_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ShippingRate::class);
            $table->decimal('from_weight_kg', 10, 3);
            $table->decimal('to_weight_kg', 10, 3)->nullable();
            $table->decimal('additional_fee', 10, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['shipping_rate_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rate_tiers');
    }
};
