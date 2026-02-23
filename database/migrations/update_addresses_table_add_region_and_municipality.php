<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Municipality, Region};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignIdFor(Region::class)->nullable()->after('province_id');
            $table->foreignIdFor(Municipality::class)->nullable()->after('region_id');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Municipality::class);
            $table->dropConstrainedForeignIdFor(Region::class);
        });
    }
};
