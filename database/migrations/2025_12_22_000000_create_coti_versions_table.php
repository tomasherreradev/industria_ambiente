<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coti_versions', function (Blueprint $table) {
            $table->id();
            $table->integer('coti_num');
            $table->integer('version');
            $table->date('fecha_version')->useCurrent();
            
            // Campos de la cotización (JSON para guardar todos los datos)
            $table->json('coti_data');
            
            // Campos de items/ensayos y componentes (JSON)
            $table->json('cotio_data')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['coti_num', 'version']);
            $table->unique(['coti_num', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coti_versions');
    }
};
