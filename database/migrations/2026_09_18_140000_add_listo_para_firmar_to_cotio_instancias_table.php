<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->boolean('listo_para_firmar')->default(false)->after('firmado');
            $table->timestamp('fecha_listo_para_firmar')->nullable()->after('listo_para_firmar');
            $table->string('listo_para_firmar_usuario', 50)->nullable()->after('fecha_listo_para_firmar');
        });
    }

    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn([
                'listo_para_firmar',
                'fecha_listo_para_firmar',
                'listo_para_firmar_usuario',
            ]);
        });
    }
};
