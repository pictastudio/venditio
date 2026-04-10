<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->boolean('is_free_gift')->default(false);
            $table->unsignedBigInteger('free_gift_id')->nullable()->index();
            $table->json('free_gift_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->dropIndex(['free_gift_id']);
            $table->dropColumn([
                'is_free_gift',
                'free_gift_id',
                'free_gift_data',
            ]);
        });
    }
};
