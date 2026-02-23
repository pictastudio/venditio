<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\Currency;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Currency::class);
            $table->string('name');
            $table->char('iso_2', 2)->unique();
            $table->char('iso_3', 3)->unique();
            $table->string('phone_code', 20);
            $table->string('flag_emoji', 50);
            $table->string('capital', 150)->nullable();
            $table->string('native', 150)->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
