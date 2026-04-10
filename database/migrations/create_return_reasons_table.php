<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_reasons');
    }
};
