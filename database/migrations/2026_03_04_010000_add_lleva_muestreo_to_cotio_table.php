<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cotio', function (Blueprint $table) {
            if (!Schema::hasColumn('cotio', 'lleva_muestreo')) {
                $table->boolean('lleva_muestreo')
                    ->default(true)
                    ->after('req_prot_mapba');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotio', function (Blueprint $table) {
            if (Schema::hasColumn('cotio', 'lleva_muestreo')) {
                $table->dropColumn('lleva_muestreo');
            }
        });
    }
};

