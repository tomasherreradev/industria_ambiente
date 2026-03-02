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
        if (!Schema::hasTable('cliente_contactos')) {
            Schema::create('cliente_contactos', function (Blueprint $table) {
                $table->id();
                // Código de cliente (mismo formato que cli.cli_codigo)
                $table->string('cli_codigo', 10)->index();
                $table->string('nombre', 120);
                $table->string('telefono', 30)->nullable();
                $table->string('email', 120)->nullable();
                // Tipo de contacto: Compras, Envío de factura, Cobranza, SHyMA, etc.
                $table->string('tipo', 30)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_contactos');
    }
};

