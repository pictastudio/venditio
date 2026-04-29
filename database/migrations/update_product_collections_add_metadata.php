<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_collections', 'metadata')) {
            return;
        }

        Schema::table('product_collections', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('product_collections', 'metadata')) {
            return;
        }

        Schema::table('product_collections', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
