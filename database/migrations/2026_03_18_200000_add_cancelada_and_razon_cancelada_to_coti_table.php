<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->boolean('cancelada')->default(false)->after('coti_estado');
            $table->text('razon_cancelada')->nullable()->after('cancelada');
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->dropColumn(['razon_cancelada', 'cancelada']);
        });
    }
};

