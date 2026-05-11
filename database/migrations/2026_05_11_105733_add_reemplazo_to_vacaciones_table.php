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
        Schema::table('vacaciones', function (Blueprint $table) {
            // Añadimos el campo de reemplazo después de los días solicitados
            $table->string('reemplazo')->nullable()->after('dias_solicitados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropColumn('reemplazo');
        });
    }
};