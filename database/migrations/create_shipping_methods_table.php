<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->boolean('active')->default(true);
            $table->decimal('flat_fee', 10, 2)->unsigned()->default(0);
            $table->decimal('volumetric_divisor', 10, 2)->unsigned()->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
