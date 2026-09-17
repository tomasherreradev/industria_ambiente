<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->date('coti_cuota_fecha_inicio')->nullable()->after('coti_cuota_fact_inicio_mes');
            $table->date('coti_cuota_fecha_fin')->nullable()->after('coti_cuota_fecha_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->dropColumn([
                'coti_cuota_fecha_inicio',
                'coti_cuota_fecha_fin',
            ]);
        });
    }
};
