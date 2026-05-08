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
        if (!Schema::hasColumn('cotio', 'de_agrupador')) {
            Schema::table('cotio', function (Blueprint $table) {
                $table->boolean('de_agrupador')->default(false)->after('cotio_nota_contenido');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('cotio', 'de_agrupador')) {
            Schema::table('cotio', function (Blueprint $table) {
                $table->dropColumn('de_agrupador');
            });
        }
    }
};
