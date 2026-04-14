<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\Order;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Order::class)->unique();
            $table->string('identifier', 100)->unique();
            $table->dateTime('issued_at');
            $table->string('currency_code', 10);
            $table->string('template_key', 100);
            $table->string('template_version', 50)->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('paper', 20)->default('a4');
            $table->string('orientation', 20)->default('portrait');
            $table->json('seller');
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();
            $table->json('lines');
            $table->json('totals');
            $table->json('payments')->nullable();
            $table->longText('rendered_html');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
