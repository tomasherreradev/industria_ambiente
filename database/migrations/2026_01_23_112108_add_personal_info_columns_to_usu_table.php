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
        Schema::table('usu', function (Blueprint $table) {
            $table->string('dni', 20)->nullable()->after('usu_descripcion');
            $table->string('email', 255)->nullable()->after('dni');
            $table->string('departamento', 255)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usu', function (Blueprint $table) {
            $table->dropColumn(['dni', 'email', 'departamento']);
        });
    }
};
