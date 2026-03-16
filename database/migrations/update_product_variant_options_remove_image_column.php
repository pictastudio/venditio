<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_variant_options', 'image')) {
            return;
        }

        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_variant_options', 'image')) {
            return;
        }

        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->string('image')->nullable()->after('name');
        });
    }
};
