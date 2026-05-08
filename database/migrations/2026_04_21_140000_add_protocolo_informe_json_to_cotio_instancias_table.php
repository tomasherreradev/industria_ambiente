<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->text('protocolo_informe_json')->nullable()->after('otn');
        });
    }

    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn('protocolo_informe_json');
        });
    }
};
