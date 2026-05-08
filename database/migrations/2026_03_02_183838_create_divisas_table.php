<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('divisas', function (Blueprint $table) {
            $table->id();
            $table->string('divisa_codigo', 10)->unique();
            $table->string('divisa_desc', 80);
        });

        DB::table('divisas')->insert([
            ['divisa_codigo' => 'PES', 'divisa_desc' => 'Pesos'],
            ['divisa_codigo' => 'USD', 'divisa_desc' => 'Dólares'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisas');
    }
};
