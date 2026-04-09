<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{ProductType, Tag};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Tag::class, 'parent_id')->nullable();
            $table->foreignIdFor(ProductType::class)->nullable();
            $table->string('path')->nullable()->index()->comment('path of the tag in the tree');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('abstract')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->json('img_thumb')->nullable();
            $table->json('img_cover')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('show_in_menu')->default(false);
            $table->boolean('in_evidence')->default(false);
            $table->smallInteger('sort_order');
            $table->dateTime('visible_from')->nullable()->index();
            $table->dateTime('visible_until')->nullable()->index();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
