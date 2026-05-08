<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (!Schema::hasColumn('coti', 'coti_mostrar_descuento')) {
                $table->boolean('coti_mostrar_descuento')
                    ->default(true)
                    ->after('coti_descuentoglobal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (Schema::hasColumn('coti', 'coti_mostrar_descuento')) {
                $table->dropColumn('coti_mostrar_descuento');
            }
        });
    }
};

