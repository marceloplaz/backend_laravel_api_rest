<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex_vacaciones', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('gestion_id')->constrained('gestiones');
            $table->foreignId('servicio_id')->constrained('servicios');

            // Campos específicos de la Tarjeta de Control (Físico)
            // Corresponde a "CAS Calificación de años de servicio"
            $table->integer('cas_calificacion')->default(20); 
            
            // Corresponde a "Vacaciones cumplidas Gestión"
            $table->integer('dias_derecho')->default(20); 

            // Sección: Vacación (DEL - AL)
            $table->date('fecha_inicio'); 
            $table->date('fecha_fin');
            
            // Corresponde a "Fecha de Retorno"
            $table->date('fecha_retorno')->nullable(); 
            
            // Corresponde a "Fecha de Solicitud / Registro"
            $table->date('fecha_solicitud')->nullable(); 

            // Contadores y Saldos
            $table->integer('dias_solicitados');
            $table->integer('saldo_restante');

            // Sección: Descripción / Observaciones
            $table->text('descripcion')->nullable(); 

            // Control de Estado (1: Pendiente, 2: Aprobado, 3: Rechazado)
            $table->integer('estado')->default(1); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex_vacaciones');
    }
};