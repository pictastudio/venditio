<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addresses')) {
            return;
        }

        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'sdi')) {
                $table->string('sdi')->nullable();
            }

            if (!Schema::hasColumn('addresses', 'pec')) {
                $table->string('pec')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('addresses')) {
            return;
        }

        Schema::table('addresses', function (Blueprint $table) {
            if (Schema::hasColumn('addresses', 'sdi')) {
                $table->dropColumn('sdi');
            }

            if (Schema::hasColumn('addresses', 'pec')) {
                $table->dropColumn('pec');
            }
        });
    }
};
