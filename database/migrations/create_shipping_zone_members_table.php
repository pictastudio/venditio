<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\ShippingZone;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_members', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ShippingZone::class);
            $table->morphs('zoneable');
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'zoneable_type', 'zoneable_id'], 'shipping_zone_members_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_members');
    }
};
