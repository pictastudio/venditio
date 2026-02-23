<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Currency, Discount, Order, Product};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Order::class);
            $table->foreignIdFor(Product::class);
            $table->foreignIdFor(Currency::class);
            $table->foreignIdFor(Discount::class)->nullable()->index();
            $table->string('product_name');
            $table->string('product_sku');
            $table->string('discount_code', 30)->nullable()->comment('discount code');
            $table->decimal('discount_amount', 10, 2)->default(0.00)->comment('absolute line discount amount');
            $table->decimal('unit_price', 10, 2)->comment('prezzo unitario di listino');
            $table->decimal('purchase_price', 10, 2)->nullable()->comment('prezzo di acquisto');
            $table->decimal('unit_discount', 10, 2)->default(0)->comment('sconto unitario');
            $table->decimal('unit_final_price', 10, 2)->comment('prezzo finale prodotto inclusi sconti');
            $table->decimal('unit_final_price_tax', 10, 2)->comment('tassa unitaria calcolata su unit_final_price');
            $table->decimal('unit_final_price_taxable', 10, 2)->comment('prezzo unitario imponibile calcolato su unit_final_price');
            $table->mediumInteger('qty')->comment('quantità ordinata');
            $table->decimal('total_final_price', 10, 2)->comment('prezzo totale finale inclusi sconti e tasse');
            $table->decimal('tax_rate', 10, 2)->comment('aliquota tassa applicata');
            $table->json('product_data')->comment('tutti i dati del prodotto salvati statici al momento dell\'ordine');
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
