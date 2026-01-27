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
        Schema::create('cliente_empresas_relacionadas', function (Blueprint $table) {
            $table->id();
            $table->string('cli_codigo', 10);
            $table->string('razon_social', 255);
            $table->string('cuit', 13)->nullable();
            $table->text('direcciones')->nullable();
            $table->string('localidad', 50)->nullable();
            $table->string('partido', 50)->nullable();
            $table->string('contacto', 100)->nullable();
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
        Schema::dropIfExists('cliente_empresas_relacionadas');
    }
};
