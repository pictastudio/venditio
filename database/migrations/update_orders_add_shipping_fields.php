<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{ShippingMethod, ShippingZone};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignIdFor(ShippingMethod::class)->nullable()->after('shipping_status_id');
            $table->foreignIdFor(ShippingZone::class)->nullable()->after('shipping_method_id');
            $table->decimal('specific_weight', 10, 2)->unsigned()->default(0)->after('shipping_fee');
            $table->decimal('volumetric_weight', 10, 2)->unsigned()->default(0)->after('specific_weight');
            $table->decimal('chargeable_weight', 10, 2)->unsigned()->default(0)->after('volumetric_weight');
            $table->json('shipping_method_data')->nullable()->after('addresses');
            $table->json('shipping_zone_data')->nullable()->after('shipping_method_data');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(ShippingZone::class);
            $table->dropConstrainedForeignIdFor(ShippingMethod::class);
            $table->dropColumn([
                'specific_weight',
                'volumetric_weight',
                'chargeable_weight',
                'shipping_method_data',
                'shipping_zone_data',
            ]);
        });
    }
};
