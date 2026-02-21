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
        Schema::create('usuario_servicios', function (Blueprint $table) {
            $table->id();
            // Relación con el usuario (médico, enfermera, etc.)
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            
            // Relación con el servicio (emergencias, pediatría, etc.)
            $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
            
            // Campos adicionales que tenías en tu diagrama
            $table->text('descripcion_usuario_servicio')->nullable();
            $table->boolean('estado')->default(true); // Para saber si sigue asignado a esa área
            $table->date('fecha_ingreso'); // Cuando empezó a trabajar en ese servicio
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_servicios');
    }
};
