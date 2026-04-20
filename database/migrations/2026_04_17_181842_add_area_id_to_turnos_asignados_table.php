<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 
    public function up(): void
    {
        Schema::table('turnos_asignados', function (Blueprint $table) {
            // Creamos la llave foránea hacia la nueva tabla 'areas'
            // La ponemos después de 'servicio_id' para mantener el orden lógico
            $table->foreignId('area_id')
                  ->after('servicio_id') 
                  ->nullable() // Lo ponemos nullable por si ya tienes datos previos
                  ->constrained('areas')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos_asignados', function (Blueprint $table) {
            // Eliminamos la relación y la columna en caso de rollback
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};