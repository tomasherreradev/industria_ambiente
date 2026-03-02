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
        Schema::table('coti', function (Blueprint $table) {
            if (!Schema::hasColumn('coti', 'coti_aumentoglobal')) {
                $table->decimal('coti_aumentoglobal', 10, 2)
                    ->nullable()
                    ->default(0.00)
                    ->after('coti_descuentoglobal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (Schema::hasColumn('coti', 'coti_aumentoglobal')) {
                $table->dropColumn('coti_aumentoglobal');
            }
        });
    }
};

