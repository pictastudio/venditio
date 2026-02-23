<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{ShippingCarrier, ShippingRate, ShippingZone};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignIdFor(ShippingCarrier::class)->nullable()->after('shipping_fee');
            $table->foreignIdFor(ShippingZone::class)->nullable()->after('shipping_carrier_id');
            $table->foreignIdFor(ShippingRate::class)->nullable()->after('shipping_zone_id');
            $table->json('shipping_quote_snapshot')->nullable()->after('shipping_rate_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(ShippingRate::class);
            $table->dropConstrainedForeignIdFor(ShippingZone::class);
            $table->dropConstrainedForeignIdFor(ShippingCarrier::class);
            $table->dropColumn('shipping_quote_snapshot');
        });
    }
};
