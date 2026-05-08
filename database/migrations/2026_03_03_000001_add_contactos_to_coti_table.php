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
            // Contacto principal - tipo
            if (!Schema::hasColumn('coti', 'coti_contacto_tipo1')) {
                $table->string('coti_contacto_tipo1', 30)->nullable()->after('coti_contacto');
            }

            // Contacto 2
            if (!Schema::hasColumn('coti', 'coti_contacto2')) {
                $table->string('coti_contacto2', 120)->nullable()->after('coti_contacto_tipo1');
            }
            if (!Schema::hasColumn('coti', 'coti_mail2')) {
                $table->string('coti_mail2', 120)->nullable()->after('coti_contacto2');
            }
            if (!Schema::hasColumn('coti', 'coti_telefono2')) {
                $table->string('coti_telefono2', 50)->nullable()->after('coti_mail2');
            }
            if (!Schema::hasColumn('coti', 'coti_contacto_tipo2')) {
                $table->string('coti_contacto_tipo2', 30)->nullable()->after('coti_telefono2');
            }

            // Contacto 3
            if (!Schema::hasColumn('coti', 'coti_contacto3')) {
                $table->string('coti_contacto3', 120)->nullable()->after('coti_contacto_tipo2');
            }
            if (!Schema::hasColumn('coti', 'coti_mail3')) {
                $table->string('coti_mail3', 120)->nullable()->after('coti_contacto3');
            }
            if (!Schema::hasColumn('coti', 'coti_telefono3')) {
                $table->string('coti_telefono3', 50)->nullable()->after('coti_mail3');
            }
            if (!Schema::hasColumn('coti', 'coti_contacto_tipo3')) {
                $table->string('coti_contacto_tipo3', 30)->nullable()->after('coti_telefono3');
            }

            // Contacto 4
            if (!Schema::hasColumn('coti', 'coti_contacto4')) {
                $table->string('coti_contacto4', 120)->nullable()->after('coti_contacto_tipo3');
            }
            if (!Schema::hasColumn('coti', 'coti_mail4')) {
                $table->string('coti_mail4', 120)->nullable()->after('coti_contacto4');
            }
            if (!Schema::hasColumn('coti', 'coti_telefono4')) {
                $table->string('coti_telefono4', 50)->nullable()->after('coti_mail4');
            }
            if (!Schema::hasColumn('coti', 'coti_contacto_tipo4')) {
                $table->string('coti_contacto_tipo4', 30)->nullable()->after('coti_telefono4');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            $columns = [
                'coti_contacto_tipo1',
                'coti_contacto2',
                'coti_mail2',
                'coti_telefono2',
                'coti_contacto_tipo2',
                'coti_contacto3',
                'coti_mail3',
                'coti_telefono3',
                'coti_contacto_tipo3',
                'coti_contacto4',
                'coti_mail4',
                'coti_telefono4',
                'coti_contacto_tipo4',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('coti', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

