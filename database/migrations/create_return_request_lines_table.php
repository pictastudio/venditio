<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{OrderLine, ReturnRequest};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ReturnRequest::class);
            $table->foreignIdFor(OrderLine::class);
            $table->unsignedMediumInteger('qty');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->unique(['return_request_id', 'order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_lines');
    }
};
