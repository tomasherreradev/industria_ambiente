<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotio', function (Blueprint $table) {
            $table->string('cotio_canal_especial', 32)->nullable()->after('lleva_muestreo');
        });
    }

    public function down(): void
    {
        Schema::table('cotio', function (Blueprint $table) {
            $table->dropColumn('cotio_canal_especial');
        });
    }
};
