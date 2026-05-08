<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usu', function (Blueprint $table) {
            if (!Schema::hasColumn('usu', 'current_session_id')) {
                $table->string('current_session_id', 120)->nullable()->after('usu_clave');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usu', function (Blueprint $table) {
            if (Schema::hasColumn('usu', 'current_session_id')) {
                $table->dropColumn('current_session_id');
            }
        });
    }
};

