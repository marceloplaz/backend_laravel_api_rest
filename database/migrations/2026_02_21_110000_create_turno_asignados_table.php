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
        // Faltaba abrir el método up() correctamente
        Schema::create('turnos_asignados', function (Blueprint $table) {
            $table->id();
            // Foreign keys a las tablas maestras
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
            $table->foreignId('turno_id')->constrained('turnos')->onDelete('cascade');
            
            // Conexiones temporales (Gestión del calendario)
            $table->foreignId('semana_id')->constrained('semanas')->onDelete('cascade');
            $table->foreignId('mes_id')->constrained('meses')->onDelete('cascade');
            $table->foreignId('gestion_id')->constrained('gestiones')->onDelete('cascade');
            
            $table->date('fecha'); // La fecha exacta del día
            $table->enum('estado', ['programado', 'asistio', 'falto', 'permiso'])->default('programado');
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cambié 'turno_asignados' por 'turnos_asignados' para que coincida
        Schema::dropIfExists('turnos_asignados');
    }
};