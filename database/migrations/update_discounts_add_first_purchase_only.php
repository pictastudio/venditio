<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->boolean('first_purchase_only')
                ->default(false)
                ->after('free_shipping')
                ->comment('if true, the discount can be used only on the first completed purchase');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn('first_purchase_only');
        });
    }
};
