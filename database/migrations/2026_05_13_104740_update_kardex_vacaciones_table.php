<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kardex_vacaciones', function (Blueprint $table) {
            // 1. Añadimos el campo que faltaba (ej: para "2020-2021")
            if (!Schema::hasColumn('kardex_vacaciones', 'gestiones_cumplidas')) {
                $table->string('gestiones_cumplidas')->after('servicio_id')->nullable();
            }

            // 2. Modificamos cas_calificacion para que sea un entero (integer)
            // Nota: Si usas una versión antigua de Laravel, podrías necesitar instalar 'doctrine/dbal'
            $table->integer('cas_calificacion')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('kardex_vacaciones', function (Blueprint $table) {
            $table->dropColumn('gestiones_cumplidas');
            $table->string('cas_calificacion')->change();
        });
    }
};