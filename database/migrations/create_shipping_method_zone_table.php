<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{ShippingMethod, ShippingZone};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_method_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ShippingMethod::class);
            $table->foreignIdFor(ShippingZone::class);
            $table->boolean('active')->default(true);
            $table->json('rate_tiers')->nullable();
            $table->decimal('over_weight_price_per_kg', 10, 2)->unsigned()->nullable();
            $table->timestamps();
            $table->unique(['shipping_method_id', 'shipping_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_method_zone');
    }
};
