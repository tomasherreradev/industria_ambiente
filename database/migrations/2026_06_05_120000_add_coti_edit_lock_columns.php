<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->string('coti_edit_lock_usu', 30)->nullable()->after('coti_version');
            $table->timestamp('coti_edit_lock_at')->nullable()->after('coti_edit_lock_usu');
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $table->dropColumn(['coti_edit_lock_usu', 'coti_edit_lock_at']);
        });
    }
};
