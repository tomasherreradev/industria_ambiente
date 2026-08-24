<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotio_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->string('cotio_numcoti', 20);
            $table->unsignedInteger('cotio_item');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('uploaded_by', 20)->nullable();
            $table->timestamps();

            $table->index(['cotio_numcoti', 'cotio_item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotio_adjuntos');
    }
};
