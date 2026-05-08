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
            $table->boolean('req_cadena_custodia')->default(false)->after('id'); // Puedes cambiar 'id' por otra columna existente
            $table->boolean('req_prot_mapba')->default(false)->after('req_cadena_custodia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotio', function (Blueprint $table) {
            $table->dropColumn(['req_cadena_custodia', 'req_prot_mapba']);
        });
    }
};