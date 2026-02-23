<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{ShippingCarrier, ShippingZone};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ShippingCarrier::class);
            $table->foreignIdFor(ShippingZone::class);
            $table->string('name');
            $table->boolean('active')->default(true)->index();
            $table->decimal('base_fee', 10, 2)->default(0);
            $table->decimal('min_order_subtotal', 10, 2)->nullable();
            $table->decimal('max_order_subtotal', 10, 2)->nullable();
            $table->unsignedSmallInteger('estimated_delivery_min_days')->nullable();
            $table->unsignedSmallInteger('estimated_delivery_max_days')->nullable();
            $table->json('metadata')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['shipping_carrier_id', 'shipping_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
