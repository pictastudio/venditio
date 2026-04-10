<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{FreeGift, User};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_gift_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(FreeGift::class);
            $table->foreignIdFor(User::class);
            $table->datetimes();
            $table->unique(['free_gift_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_gift_user');
    }
};
