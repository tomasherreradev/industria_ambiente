<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->boolean('facturacion_aprobada')->default(false)->after('aprobado_informe_usuario');
            $table->timestamp('fecha_facturacion_aprobada')->nullable()->after('facturacion_aprobada');
            $table->string('facturacion_aprobada_usuario', 50)->nullable()->after('fecha_facturacion_aprobada');
        });

        // Muestras ya en cola de facturación antes del cambio: mantenerlas visibles en /facturacion.
        DB::table('cotio_instancias')
            ->where('enable_inform', true)
            ->where('facturado', false)
            ->where('cotio_subitem', 0)
            ->update(['facturacion_aprobada' => true]);
    }

    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn([
                'facturacion_aprobada',
                'fecha_facturacion_aprobada',
                'facturacion_aprobada_usuario',
            ]);
        });
    }
};
