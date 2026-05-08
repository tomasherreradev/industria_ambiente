<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->boolean('coti_req_cadena_custodia_relacionada')->default(false)->after('coti_muestreo');
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->dropColumn('coti_req_cadena_custodia_relacionada');
        });
    }
};
