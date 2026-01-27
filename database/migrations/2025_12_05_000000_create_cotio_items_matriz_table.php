<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotio_items_matriz', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotio_item_id');
            $table->string('matriz_codigo', 10);
            $table->timestamps();

            $table->index('cotio_item_id');
            $table->index('matriz_codigo');
            $table->unique(['cotio_item_id', 'matriz_codigo']);

            // Nota: No se usan foreign keys porque cotio_items es una tabla legacy
            // que puede no tener restricciones de integridad referencial.
            // La integridad se mantiene a nivel de aplicación.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotio_items_matriz');
    }
};
