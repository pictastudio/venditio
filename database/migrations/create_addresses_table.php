<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Country, Province};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('addressable');
            $table->foreignIdFor(Country::class)->nullable();
            $table->foreignIdFor(Province::class)->nullable();
            $table->string('type', 30)->comment('billing, shipping')->index();
            $table->boolean('is_default')->default(0);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('sex', 2);
            $table->string('phone', 50);
            $table->string('vat_number', 25)->nullable();
            $table->string('fiscal_code', 25);
            $table->string('sdi')->nullable();
            $table->string('pec')->nullable();
            $table->string('company_name')->nullable();
            $table->string('address_line_1')->comment('street address, including the house number and street name');
            $table->string('address_line_2')->nullable()->comment('apartment, suite, unit, building, floor, etc.');
            $table->string('city', 100);
            $table->string('state', 10);
            $table->string('zip', 10);
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->text('notes')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
