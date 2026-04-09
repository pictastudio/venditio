<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Country, ShippingZone};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_country', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ShippingZone::class);
            $table->foreignIdFor(Country::class);
            $table->timestamps();
            $table->unique(['shipping_zone_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_country');
    }
};
