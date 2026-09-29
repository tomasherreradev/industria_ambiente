<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usu', function (Blueprint $table) {
            $table->boolean('puede_autorizar_facturacion')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('usu', function (Blueprint $table) {
            $table->dropColumn('puede_autorizar_facturacion');
        });
    }
};
