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
        Schema::table('cotio_items', function (Blueprint $table) {
            $table->boolean('agregable_a_comps')->default(false)->after('es_muestra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotio_items', function (Blueprint $table) {
            $table->dropColumn('agregable_a_comps');
        });
    }
};
