<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_applications', function (Blueprint $table) {
            $table->dropUnique('discount_applications_order_line_id_unique');
            $table->index('order_line_id');
            $table->unique(
                ['discount_id', 'order_line_id'],
                'discount_applications_discount_id_order_line_id_unique'
            );
        });
    }

    public function down(): void
    {
        // Intentionally left empty to avoid re-introducing single-discount-per-line constraints.
    }
};
