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
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('usu_codigo');
            $table->string('rol');
            $table->timestamps();
            
            // Clave foránea hacia la tabla usu
            $table->foreign('usu_codigo')
                  ->references('usu_codigo')
                  ->on('usu')
                  ->onDelete('cascade');
            
            // Índice único para evitar roles duplicados por usuario
            $table->unique(['usu_codigo', 'rol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
