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
        Schema::table('cotio', function (Blueprint $table) {
            $table->string('cotio_nota_tipo', 20)->nullable()->after('ley_aplicacion')->comment('Tipo de nota: imprimible, interna, fact');
            $table->text('cotio_nota_contenido')->nullable()->after('cotio_nota_tipo')->comment('Contenido de la nota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotio', function (Blueprint $table) {
            $table->dropColumn(['cotio_nota_tipo', 'cotio_nota_contenido']);
        });
    }
};
