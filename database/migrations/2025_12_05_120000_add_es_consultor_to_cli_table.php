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
        Schema::table('cli', function (Blueprint $table) {
            $table->boolean('es_consultor')->default(false)->after('cli_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cli', function (Blueprint $table) {
            $table->dropColumn('es_consultor');
        });
    }
};
