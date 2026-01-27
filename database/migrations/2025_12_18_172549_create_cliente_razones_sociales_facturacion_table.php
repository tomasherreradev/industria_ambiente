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
        Schema::create('cliente_razones_sociales_facturacion', function (Blueprint $table) {
            $table->id();
            $table->string('cli_codigo', 10);
            $table->string('razon_social', 255);
            $table->string('cuit', 13)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('condicion_iva', 5)->nullable();
            $table->string('condicion_iva_desc', 100)->nullable();
            $table->string('condicion_pago', 5)->nullable();
            $table->string('condicion_pago_desc', 100)->nullable();
            $table->string('tipo_factura', 25)->nullable();
            $table->boolean('es_predeterminada')->default(false);
            $table->timestamps();

            $table->foreign('cli_codigo')->references('cli_codigo')->on('cli')->onDelete('cascade');
            $table->index('cli_codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_razones_sociales_facturacion');
    }
};
