<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usu', function (Blueprint $table) {
            $table->boolean('admin_lab')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('usu', function (Blueprint $table) {
            $table->dropColumn('admin_lab');
        });
    }
};
