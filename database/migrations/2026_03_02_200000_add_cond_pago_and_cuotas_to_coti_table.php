<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->string('coti_cond_pago', 10)->nullable()->after('divisa_codigo');
            $table->boolean('coti_cuotas')->default(false)->after('coti_cond_pago');
            $table->string('coti_cuota_desc', 100)->nullable()->after('coti_cuotas');
            $table->unsignedSmallInteger('coti_cuota_cant')->nullable()->after('coti_cuota_desc');
            $table->decimal('coti_cuota_monto_total', 15, 4)->nullable()->after('coti_cuota_cant');
            $table->decimal('coti_cuota_monto_indiv', 15, 4)->nullable()->after('coti_cuota_monto_total');
            $table->boolean('coti_cuota_fact_fin_mes')->nullable()->after('coti_cuota_monto_indiv');
            $table->boolean('coti_cuota_fact_inicio_mes')->nullable()->after('coti_cuota_fact_fin_mes');
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->dropColumn([
                'coti_cond_pago',
                'coti_cuotas',
                'coti_cuota_desc',
                'coti_cuota_cant',
                'coti_cuota_monto_total',
                'coti_cuota_monto_indiv',
                'coti_cuota_fact_fin_mes',
                'coti_cuota_fact_inicio_mes',
            ]);
        });
    }
};
