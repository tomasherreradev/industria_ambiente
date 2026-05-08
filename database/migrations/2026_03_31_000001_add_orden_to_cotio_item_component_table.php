<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotio_item_component', function (Blueprint $table) {
            if (!Schema::hasColumn('cotio_item_component', 'orden')) {
                $table->unsignedInteger('orden')->nullable()->after('componente_id');
                $table->index(['agrupador_id', 'orden']);
            }
        });

        // Backfill: asignar orden por (agrupador_id) respetando el orden de creación (id asc).
        // Postgres: usa window function.
        DB::statement("
            WITH ranked AS (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY agrupador_id ORDER BY id ASC) AS rn
                FROM cotio_item_component
                WHERE orden IS NULL
            )
            UPDATE cotio_item_component c
            SET orden = r.rn
            FROM ranked r
            WHERE c.id = r.id
        ");
    }

    public function down(): void
    {
        Schema::table('cotio_item_component', function (Blueprint $table) {
            if (Schema::hasColumn('cotio_item_component', 'orden')) {
                $table->dropIndex(['agrupador_id', 'orden']);
                $table->dropColumn('orden');
            }
        });
    }
};

