<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Order, ReturnReason, User};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Order::class);
            $table->foreignIdFor(User::class)->nullable();
            $table->foreignIdFor(ReturnReason::class);
            $table->json('billing_address');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_accepted')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
