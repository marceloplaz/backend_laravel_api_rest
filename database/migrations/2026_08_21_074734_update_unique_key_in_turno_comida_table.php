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
        Schema::table('turno_comida', function (Blueprint $table) {
            // 1. Asegurar que la columna existe
            if (!Schema::hasColumn('turno_comida', 'dia_relativo')) {
                $table->integer('dia_relativo')->default(0)->after('comida_id');
            }

            // 2. CREAR PRIMERO el nuevo índice único compuesto
            $table->unique(['turno_id', 'comida_id', 'dia_relativo'], 'turno_comida_unique');

            // 3. ELIMINAR DESPUÉS el índice antiguo (MySQL ya no marcará error 1553)
            $table->dropUnique('turno_comida_turno_id_comida_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turno_comida', function (Blueprint $table) {
            $table->unique(['turno_id', 'comida_id'], 'turno_comida_turno_id_comida_id_unique');
            $table->dropUnique('turno_comida_unique');
        });
    }
};