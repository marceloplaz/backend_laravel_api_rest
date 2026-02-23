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
    Schema::create('incidencias_tecnicas', function (Blueprint $table) {
        $table->id();
        // El usuario que reporta (médico/encargado)
        $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
        // El servicio afectado (Emergencias, Pediatría, etc.)
        $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
        
        $table->string('categoria'); // luz, agua, equipo medico, computacion
        $table->date('fecha');       // Para vincularlo al día del calendario
        $table->text('descripcion');
        
        // Gestión de la solución
        $table->enum('prioridad', ['baja', 'media', 'alta'])->default('media');
        $table->enum('estado', ['pendiente', 'en_proceso', 'solucionado'])->default('pendiente');
        $table->text('observacion_tecnica')->nullable(); 
        $table->timestamp('fecha_solucion')->nullable();
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidencias_tecnicas');
    }
};
