<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (!Schema::hasColumn('coti', 'coti_creador')) {
                $table->string('coti_creador', 20)->nullable()->after('coti_responsable');
                $table->index('coti_creador');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (Schema::hasColumn('coti', 'coti_creador')) {
                $table->dropIndex(['coti_creador']);
                $table->dropColumn('coti_creador');
            }
        });
    }
};

