<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('venditio.wishlists.tables.wishlists', 'wishlists'), function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('venditio.wishlists.tables.wishlists', 'wishlists'));
    }
};
