<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (! Schema::hasColumn('coti', 'coti_oc_requerido_factura')) {
                $table->boolean('coti_oc_requerido_factura')->default(false);
            }
            if (! Schema::hasColumn('coti', 'coti_refs_facturacion_json')) {
                $table->json('coti_refs_facturacion_json')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('coti', function (Blueprint $table) {
            if (Schema::hasColumn('coti', 'coti_refs_facturacion_json')) {
                $table->dropColumn('coti_refs_facturacion_json');
            }
            if (Schema::hasColumn('coti', 'coti_oc_requerido_factura')) {
                $table->dropColumn('coti_oc_requerido_factura');
            }
        });
    }
};
