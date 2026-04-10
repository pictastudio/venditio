<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_gifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mode', 20)->comment('automatic, manual');
            $table->string('selection_mode', 20)->comment('single, multiple');
            $table->boolean('allow_decline')->default(false);
            $table->boolean('active')->default(true);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->decimal('minimum_cart_subtotal', 10, 2)->nullable();
            $table->decimal('maximum_cart_subtotal', 10, 2)->nullable();
            $table->unsignedInteger('minimum_cart_quantity')->nullable();
            $table->unsignedInteger('maximum_cart_quantity')->nullable();
            $table->string('product_match_mode', 20)->default('any')->comment('any, all');
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_gifts');
    }
};
