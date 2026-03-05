<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\ProductTag;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductTag::class);
            $table->morphs('taggable');
            $table->datetimes();

            $table->unique([
                'product_tag_id',
                'taggable_type',
                'taggable_id',
            ], 'taggables_unique_association');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
