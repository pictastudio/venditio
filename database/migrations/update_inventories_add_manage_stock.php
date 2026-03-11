<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->boolean('manage_stock')
                ->default(true)
                ->after('stock_min')
                ->comment('when false, stock is not validated, reserved, or decremented automatically');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('manage_stock');
        });
    }
};
