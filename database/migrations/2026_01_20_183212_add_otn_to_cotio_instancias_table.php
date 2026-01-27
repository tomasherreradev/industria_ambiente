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
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->string('otn', 10)->nullable()->after('enable_ot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn('otn');
        });
    }
};
