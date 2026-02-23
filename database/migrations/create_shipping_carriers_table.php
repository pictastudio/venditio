<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_carriers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('active')->default(true)->index();
            $table->decimal('volumetric_divisor', 10, 2)->default(5000);
            $table->decimal('weight_rounding_step_kg', 8, 3)->default(0.500);
            $table->string('weight_rounding_mode', 20)->default('ceil');
            $table->json('metadata')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_carriers');
    }
};
