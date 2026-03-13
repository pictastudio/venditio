<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->mediumInteger('minimum_reorder_quantity')
                ->nullable()
                ->after('stock_min')
                ->comment('minimum quantity to use when reordering stock');

            $table->mediumInteger('reorder_lead_days')
                ->nullable()
                ->after('minimum_reorder_quantity')
                ->comment('lead time in days required to replenish stock');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn([
                'minimum_reorder_quantity',
                'reorder_lead_days',
            ]);
        });
    }
};
