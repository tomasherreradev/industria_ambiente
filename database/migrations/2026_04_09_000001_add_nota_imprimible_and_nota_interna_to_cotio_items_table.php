<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notas por defecto al usar la determinación en cotizaciones (imprimible / interna).
     */
    public function up(): void
    {
        Schema::table('cotio_items', function (Blueprint $table) {
            $table->text('nota_imprimible')->nullable()->after('precio');
            $table->text('nota_interna')->nullable()->after('nota_imprimible');
        });
    }

    public function down(): void
    {
        Schema::table('cotio_items', function (Blueprint $table) {
            $table->dropColumn(['nota_imprimible', 'nota_interna']);
        });
    }
};
