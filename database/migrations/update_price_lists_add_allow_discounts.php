<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('price_lists', 'allow_discounts')) {
            return;
        }

        Schema::table('price_lists', function (Blueprint $table) {
            $table->boolean('allow_discounts')
                ->default(true)
                ->after('active');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('price_lists', 'allow_discounts')) {
            return;
        }

        Schema::table('price_lists', function (Blueprint $table) {
            $table->dropColumn('allow_discounts');
        });
    }
};
