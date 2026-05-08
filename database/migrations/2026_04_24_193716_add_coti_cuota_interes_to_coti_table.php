<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            // Porcentaje de interés aplicado sobre el total antes de dividir en cuotas.
            // Ej: 10 = 10%. null = sin interés (equivalente a 0).
            $table->decimal('coti_cuota_interes', 8, 4)->nullable()->default(null)->after('coti_cuota_fact_inicio_mes');
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->dropColumn('coti_cuota_interes');
        });
    }
};
