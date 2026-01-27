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
        Schema::table('coti', function (Blueprint $table) {
            $table->unsignedBigInteger('coti_cli_empresa')->nullable()->after('coti_para');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->dropColumn('coti_cli_empresa');
        });
    }
};
