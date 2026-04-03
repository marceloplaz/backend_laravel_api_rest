<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos_asignados', function (Blueprint $table) {
            // Ampliamos a 50 caracteres para que quepa "baja_medica"
            $table->string('estado', 50)->default('normal')->change();
        });
    }

    public function down(): void
    {
        Schema::table('turnos_asignados', function (Blueprint $table) {
            // Volver al tamaño original (ejemplo: 20) si reviertes la migración
            $table->string('estado', 20)->change();
        });
    }
};