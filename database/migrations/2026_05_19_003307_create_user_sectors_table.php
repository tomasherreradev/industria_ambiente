<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('usu_codigo');
            $table->string('sector_codigo');
            $table->timestamps();

            // Claves foráneas hacia la tabla usu
            $table->foreign('usu_codigo')
                  ->references('usu_codigo')
                  ->on('usu')
                  ->onDelete('cascade');

            $table->foreign('sector_codigo')
                  ->references('usu_codigo')
                  ->on('usu')
                  ->onDelete('cascade');

            // Índice único para evitar sectores duplicados por usuario
            $table->unique(['usu_codigo', 'sector_codigo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sectors');
    }
};
