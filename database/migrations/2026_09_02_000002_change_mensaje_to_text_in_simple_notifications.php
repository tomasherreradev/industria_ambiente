<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simple_notifications', function (Blueprint $table) {
            $table->text('mensaje')->change();
        });
    }

    public function down(): void
    {
        Schema::table('simple_notifications', function (Blueprint $table) {
            $table->string('mensaje')->change();
        });
    }
};
