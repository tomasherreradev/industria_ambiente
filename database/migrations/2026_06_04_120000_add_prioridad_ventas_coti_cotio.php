<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (! Schema::hasColumn('coti', 'coti_prioridad_global')) {
                $table->boolean('coti_prioridad_global')->default(false);
            }
        });

        Schema::table('cotio', function (Blueprint $table) {
            if (! Schema::hasColumn('cotio', 'es_priori')) {
                $table->boolean('es_priori')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (Schema::hasColumn('coti', 'coti_prioridad_global')) {
                $table->dropColumn('coti_prioridad_global');
            }
        });

        Schema::table('cotio', function (Blueprint $table) {
            if (Schema::hasColumn('cotio', 'es_priori')) {
                $table->dropColumn('es_priori');
            }
        });
    }
};
