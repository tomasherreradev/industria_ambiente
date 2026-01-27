<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->boolean('coti_cadena_custodia')->default(false)->after('coti_sector_cromatografia_observaciones');
            $table->boolean('coti_muestreo')->default(false)->after('coti_cadena_custodia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->dropColumn(['coti_cadena_custodia', 'coti_muestreo']);
        });
    }
};
