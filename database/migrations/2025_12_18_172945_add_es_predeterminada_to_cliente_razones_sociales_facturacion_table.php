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
        Schema::table('cliente_razones_sociales_facturacion', function (Blueprint $table) {
            if (!Schema::hasColumn('cliente_razones_sociales_facturacion', 'es_predeterminada')) {
                $table->boolean('es_predeterminada')->default(false)->after('tipo_factura');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cliente_razones_sociales_facturacion', function (Blueprint $table) {
            if (Schema::hasColumn('cliente_razones_sociales_facturacion', 'es_predeterminada')) {
                $table->dropColumn('es_predeterminada');
            }
        });
    }
};
