<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (!Schema::hasColumn('coti', 'coti_para_empresa_rel')) {
                $table->boolean('coti_para_empresa_rel')->default(false)->after('coti_cli_empresa');
            }
            if (!Schema::hasColumn('coti', 'coti_empresa_rel')) {
                $table->unsignedBigInteger('coti_empresa_rel')->nullable()->after('coti_para_empresa_rel');
            }
        });

        if (Schema::hasColumn('coti', 'coti_cli_empresa') && Schema::hasColumn('coti', 'coti_empresa_rel')) {
            DB::statement('UPDATE coti SET coti_empresa_rel = coti_cli_empresa WHERE coti_cli_empresa IS NOT NULL AND coti_empresa_rel IS NULL');
            DB::statement('UPDATE coti SET coti_para_empresa_rel = TRUE WHERE coti_cli_empresa IS NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (Schema::hasColumn('coti', 'coti_empresa_rel')) {
                $table->dropColumn('coti_empresa_rel');
            }
            if (Schema::hasColumn('coti', 'coti_para_empresa_rel')) {
                $table->dropColumn('coti_para_empresa_rel');
            }
        });
    }
};
