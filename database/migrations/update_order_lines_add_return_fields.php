<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->unsignedInteger('requested_return_qty')->default(0)->after('product_data');
            $table->unsignedInteger('returned_qty')->default(0)->after('requested_return_qty');
            $table->boolean('has_return_requests')->default(false)->after('returned_qty');
            $table->boolean('is_returned')->default(false)->after('has_return_requests');
            $table->boolean('is_fully_returned')->default(false)->after('is_returned');
        });
    }

    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn([
                'requested_return_qty',
                'returned_qty',
                'has_return_requests',
                'is_returned',
                'is_fully_returned',
            ]);
        });
    }
};
