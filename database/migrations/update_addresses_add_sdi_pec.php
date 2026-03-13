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

        if (!Schema::hasColumn('addresses', 'sdi')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('sdi')->nullable()->after('fiscal_code');
            });
        }

        if (!Schema::hasColumn('addresses', 'pec')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('pec')->nullable()->after(
                    Schema::hasColumn('addresses', 'sdi') ? 'sdi' : 'fiscal_code'
                );
            });
        }
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
