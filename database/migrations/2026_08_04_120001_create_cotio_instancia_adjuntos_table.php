<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotio_instancia_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotio_instancia_id');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('context', 50)->default('revision_coord');
            $table->string('uploaded_by', 20)->nullable();
            $table->timestamps();

            $table->foreign('cotio_instancia_id')
                ->references('id')
                ->on('cotio_instancias')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotio_instancia_adjuntos');
    }
};
