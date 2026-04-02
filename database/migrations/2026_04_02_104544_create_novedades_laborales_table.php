<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('novedades_laborales', function (Blueprint $blueprint) {
            $blueprint->id();
            
            // Relación con el turno original en HeidiSQL
            $blueprint->foreignId('asignacion_id')->constrained('turnos_asignados')->onDelete('cascade');
            
            // El tipo de novedad que vimos en tu Excel (baja, permiso, vacación)
            $blueprint->enum('tipo_novedad', [
                'permiso', 
                'baja_medica', 
                'vacacion', 
                'licencia', 
                'devolucion_turno'
            ]);

            // Quién solicita y quién cubre (Reemplazo)
            $blueprint->foreignId('usuario_solicitante_id')->constrained('users');
            $blueprint->foreignId('usuario_reemplazo_id')->nullable()->constrained('users');

            // Campos para el intercambio físico de fechas
            $blueprint->date('fecha_original');
            $blueprint->date('fecha_nueva')->nullable();

            // Lógica administrativa
            $blueprint->boolean('con_devolucion')->default(false); // ¿Debe devolver el turno?
            $blueprint->string('documento_respaldo')->nullable(); // Para subir PDF/Foto de la baja
            $blueprint->text('observacion_detalle')->nullable();

            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novedades_laborales');
    }
};