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
    // Cambiar 'turno_asignados' por 'turnos_asignados'
    Schema::table('turnos_asignados', function (Blueprint $table) {
        $table->boolean('es_intercambio')->default(false)->after('estado');
    });
}

public function down(): void
{
    // Cambiar 'turno_asignados' por 'turnos_asignados'
    Schema::table('turnos_asignados', function (Blueprint $table) {
        $table->dropColumn('es_intercambio');
    });
}
};
